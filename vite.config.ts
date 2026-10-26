import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import fs from 'fs';
import laravel from 'laravel-vite-plugin';
import os from 'os';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig, loadEnv, type Plugin } from 'vite';
import { brotliCompressSync, gzipSync, constants as zlibConstants } from 'zlib';

function getLocalIp(): string {
    const interfaces = os.networkInterfaces();
    for (const name of Object.keys(interfaces)) {
        for (const iface of interfaces[name] || []) {
            if (iface.family === 'IPv4' && !iface.internal) {
                return iface.address;
            }
        }
    }
    return 'localhost';
}

/**
 * Write pre-compressed copies (.gz and .br) of the built assets so the LAN server
 * (server.php) sends 70-80% fewer bytes over slow school Wi-Fi without having
 * to compress on every request. Runs after the bundle is written, so the
 * compressed files always match the final output byte for byte.
 */
function precompressAssets(): Plugin {
    const compressible = /\.(js|mjs|css|svg|json|txt)$/i;

    return {
        name: 'alsenform:precompress-assets',
        apply: 'build',
        enforce: 'post',
        writeBundle(options, bundle) {
            const outDir = options.dir ?? path.resolve(__dirname, 'public/build');

            for (const fileName of Object.keys(bundle)) {
                if (!compressible.test(fileName)) {
                    continue;
                }

                const filePath = path.join(outDir, fileName);
                const source = fs.readFileSync(filePath);
                if (source.length < 1024) {
                    continue;
                }

                fs.writeFileSync(`${filePath}.gz`, gzipSync(source, { level: 9 }));
                fs.writeFileSync(`${filePath}.br`, brotliCompressSync(source, { params: { [zlibConstants.BROTLI_PARAM_QUALITY]: 11 } }));
            }
        },
    };
}

/**
 * The oldest browsers the pages must open on: school tablets and phones that no longer update,
 * like iPads on iOS 12 and Android tablets with Chrome 73. Newer syntax is rewritten for them;
 * the few newer functions they lack are filled in by resources/views/app.blade.php.
 */
const BROWSER_TARGETS = ['chrome73', 'edge79', 'firefox68', 'safari12.1', 'ios12.2'];

/**
 * Fail the build when a bundle holds a regular expression lookbehind, (?<= or (?<!. Safari before
 * 16.4 cannot read it, so every page using that bundle stayed blank white on older iPhones and
 * iPads; rewritten for old browsers it still throws when it runs.
 */
function guardOldBrowsers(): Plugin {
    return {
        name: 'alsenform:guard-old-browsers',
        apply: 'build',
        generateBundle(options, bundle) {
            for (const chunk of Object.values(bundle)) {
                if (chunk.type === 'chunk' && /\(\?<[=!]/.test(chunk.code)) {
                    this.error(
                        `${chunk.fileName} memakai regex lookbehind (?<= atau (?<!), yang membuat halaman putih di Safari sebelum 16.4. Tulis ulang regex itu tanpa lookbehind.`,
                    );
                }
            }
        },
    };
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        server: {
            host: '0.0.0.0',
            hmr: {
                host: env.VITE_HMR_HOST || getLocalIp(),
            },
            cors: true,
        },
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            guardOldBrowsers(),
            precompressAssets(),
        ],
        build: {
            target: BROWSER_TARGETS,
        },
        resolve: {
            alias: {
                '@': path.resolve(__dirname, './resources/js'),
            },
        },
        css: {
            postcss: {
                plugins: [tailwindcss, autoprefixer],
            },
        },
    };
});
