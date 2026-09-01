import { sleep } from 'k6';
import { baseUrl, hitReadPaths } from './lib/pages.js';

export const options = {
    vus: 2,
    duration: '30s',
    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<1500'],
        checks: ['rate>0.99'],
    },
};

export default function () {
    hitReadPaths(baseUrl());
    sleep(1);
}
