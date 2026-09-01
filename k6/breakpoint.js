import { sleep } from 'k6';
import { baseUrl, hitReadPaths } from './lib/pages.js';

export const options = {
    scenarios: {
        breakpoint: {
            executor: 'ramping-arrival-rate',
            startRate: 2,
            timeUnit: '1s',
            preAllocatedVUs: 20,
            maxVUs: 80,
            stages: [
                { duration: '1m', target: 5 },
                { duration: '1m', target: 10 },
                { duration: '1m', target: 20 },
                { duration: '1m', target: 40 },
                { duration: '1m', target: 60 },
            ],
        },
    },
    thresholds: {
        http_req_failed: [{ threshold: 'rate<0.05', abortOnFail: true }],
        checks: [{ threshold: 'rate>0.95', abortOnFail: true }],
    },
};

export default function () {
    hitReadPaths(baseUrl());
    sleep(0.5);
}
