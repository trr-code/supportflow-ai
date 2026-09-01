import { sleep } from 'k6';
import { baseUrl, hitReadPaths } from './lib/pages.js';

export const options = {
    stages: [
        { duration: '1m', target: 15 },
        { duration: '2m', target: 25 },
        { duration: '2m', target: 25 },
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        http_req_failed: ['rate<0.05'],
        http_req_duration: ['p(95)<3000'],
        checks: ['rate>0.95'],
    },
};

export default function () {
    hitReadPaths(baseUrl());
    sleep(1);
}
