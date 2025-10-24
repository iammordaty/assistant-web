/* global $ */

export default function (ss) {
    let result = '',
        s,
        m,
        h,
        d;

    s = Math.floor(ss % 60);
    m = Math.floor((ss % 3600) / 60);
    h = Math.floor((ss % 86400) / 3600);
    d = Math.floor((ss % 2592000) / 86400);

    if (d > 0) {
        result += d + ':';
    }

    if (h > 0) {
        result += (h < 10 ? '0' : '') + h + ':';
    }

    result += (m < 10 ? '0' : '') + m + ':';
    result += (s < 10 ? '0' : '') + s;

    return (ss < 0 ? '-' : '') + result;
}
