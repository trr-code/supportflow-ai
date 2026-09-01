import http from 'k6/http';
import { check, fail } from 'k6';

export function baseUrl() {
    const url = __ENV.BASE_URL;

    if (!url) {
        fail('Set BASE_URL, for example BASE_URL=https://example.com k6 run k6/smoke.js');
    }

    return String(url).replace(/\/$/, '');
}

export const readPaths = ['/up', '/', '/knowledge', '/knowledge/return-window'];

export function hitReadPaths(root) {
    const responses = http.batch(readPaths.map((path) => ['GET', `${root}${path}`]));

    responses.forEach((res, index) => {
        const path = readPaths[index];

        check(res, {
            [`${path} is 200`]: (r) => r.status === 200,
        });
    });
}
