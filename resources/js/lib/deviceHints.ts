import axios from 'axios';

/**
 * What the browser can tell about the device beyond its User-Agent, sent with every request (the
 * X-Device-Hints header) so the activity report records the device exactly:
 * - over HTTPS, the real phone model, operating system version and full browser version, which
 *   Chrome and Edge hide from their User-Agent (navigator.userAgentData);
 * - on any connection, the touch screen and processor (an iPad that says it is a Mac, an Android
 *   tablet in desktop mode) and Brave, which looks like Chrome.
 * Read on the server by App\Support\UserAgent::hintsFrom().
 */
type DeviceHints = {
    touch: number;
    platform: string;
    screen: [number, number];
    brave?: true;
    model?: string;
    platformName?: string;
    platformVersion?: string;
    mobile?: boolean;
    browsers?: { brand: string; version: string }[];
};

type HighEntropyValues = {
    model?: string;
    platform?: string;
    platformVersion?: string;
    mobile?: boolean;
    fullVersionList?: { brand: string; version: string }[];
};

let headerValue = '';

const basicHints = (): DeviceHints => ({
    touch: navigator.maxTouchPoints ?? 0,
    platform: navigator.platform ?? '',
    screen: [window.screen?.width ?? 0, window.screen?.height ?? 0],
    ...('brave' in navigator ? { brave: true as const } : {}),
});

const encode = (hints: DeviceHints): string => {
    try {
        return encodeURIComponent(JSON.stringify(hints));
    } catch {
        return '';
    }
};

/** The device report for requests the page sends itself (fetch with keepalive). */
export const deviceHintsHeader = (): string => headerValue;

export function setupDeviceHints(): void {
    if (typeof navigator === 'undefined' || typeof window === 'undefined') {
        return;
    }

    headerValue = encode(basicHints());

    const userAgentData = (navigator as Navigator & { userAgentData?: { getHighEntropyValues?: (hints: string[]) => Promise<HighEntropyValues> } })
        .userAgentData;
    userAgentData
        ?.getHighEntropyValues?.(['model', 'platformVersion', 'fullVersionList'])
        .then((values) => {
            headerValue = encode({
                ...basicHints(),
                ...(values.model ? { model: values.model } : {}),
                ...(values.platform ? { platformName: values.platform } : {}),
                ...(values.platformVersion ? { platformVersion: values.platformVersion } : {}),
                ...(typeof values.mobile === 'boolean' ? { mobile: values.mobile } : {}),
                ...(values.fullVersionList ? { browsers: values.fullVersionList.map(({ brand, version }) => ({ brand, version })) } : {}),
            });
        })
        .catch(() => {
            // Not allowed (plain HTTP) or not supported: the basic report is sent.
        });

    axios.interceptors.request.use((config) => {
        if (headerValue) {
            config.headers.set('X-Device-Hints', headerValue);
        }
        return config;
    });
}
