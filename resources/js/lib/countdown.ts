/**
 * The time left of an exam as people say it: "1 jam 5 menit" from an hour on, "12 menit 30 detik"
 * under an hour, "45 detik" in the last minute. A part that is 0 is left out ("2 jam", "5 menit").
 */
export const formatCountdown = (remainingMs: number): string => {
    const totalSeconds = Math.max(0, Math.floor(remainingMs / 1000));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (hours > 0) {
        return minutes > 0 ? `${hours} jam ${minutes} menit` : `${hours} jam`;
    }
    if (minutes > 0) {
        return seconds > 0 ? `${minutes} menit ${seconds} detik` : `${minutes} menit`;
    }

    return `${seconds} detik`;
};
