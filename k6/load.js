import { sleep } from 'k6';
import { baseUrl, hitReadPaths } from './lib/pages.js';

export const options = {
    stages: [
        { duration: '1m', target: 8 },
        { duration: '3m', target: 8 },
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        http_req_failed: ['rate<0.02'],
        http_req_duration: ['p(95)<2000'],
        checks: ['rate>0.98'],
    },
};

export default function () {
    hitReadPaths(baseUrl());
    sleep(1);
}
