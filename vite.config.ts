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
            precompressAssets(),
        ],
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
