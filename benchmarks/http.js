import http from 'k6/http';
import { check } from 'k6';

const cases = {
    plaintext: { path: '/plaintext', status: 200, body: 'Hello, World!', type: 'text/plain' },
    json: { path: '/json', status: 200, body: '{"message":"Hello, World!"}', type: 'application/json' },
    parameter: { path: '/users/42', status: 200, body: '{"id":"42"}', type: 'application/json' },
    missing: { path: '/missing', status: 404, body: 'Not Found', type: 'text/plain' },
};
const workload = cases[__ENV.WORKLOAD || 'plaintext'];
if (!workload) {
    throw new Error('Unknown WORKLOAD');
}

export const options = {
    scenarios: {
        requests: {
            executor: 'constant-vus',
            vus: Number(__ENV.VUS || 16),
            duration: __ENV.DURATION || '10s',
            gracefulStop: '2s',
        },
    },
    noConnectionReuse: true,
    maxRedirects: 0,
    thresholds: {
        checks: ['rate==1'],
        http_req_failed: ['rate==0'],
    },
    summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
};

http.setResponseCallback(http.expectedStatuses(workload.status));

export default function request() {
    const response = http.get(`${__ENV.BASE_URL || 'http://127.0.0.1:8091'}${workload.path}`, {
        timeout: '2s',
    });
    check(response, {
        'correct status': (result) => result.status === workload.status,
        'correct body': (result) => result.body === workload.body,
        'correct content type': (result) =>
            (result.headers['Content-Type'] || '').split(';')[0] === workload.type,
        'correct feature header': (result) => __ENV.BENCH_PROFILE === 'features'
            ? result.headers['X-Benchmark-Feature'] === 'service:enabled'
            : result.headers['X-Benchmark-Feature'] === undefined,
    });
}

export function handleSummary(data) {
    return { [__ENV.SUMMARY || 'summary.json']: JSON.stringify(data, null, 2) };
}
