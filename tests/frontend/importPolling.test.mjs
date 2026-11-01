import { test } from 'node:test';
import assert from 'node:assert/strict';
import { startImportPolling } from '../../resources/js/Components/importPolling.js';
const flush = () => new Promise(resolve => setImmediate(resolve));
function harness(fetchStatus) {
    const statuses=[],errors=[],scheduled=[];
    const stop=startImportPolling({fetchStatus,onStatus:s=>statuses.push(s),onError:e=>errors.push(e),schedule:(fn,delay)=>{scheduled.push({fn,delay});return scheduled.length;},cancel:()=>{}});
    return {statuses,errors,scheduled,stop};
}
test('network failure retries then renders completed counts without repeating import',async()=>{
    let calls=0;const h=harness(async()=>{if(++calls===1)throw new Error('offline');return {status:'completed',processedRows:2,regularCount:1,irregularCount:1};});
    await flush();assert.match(h.errors[0],/Retrying/);assert.equal(h.scheduled.length,1);
    await h.scheduled[0].fn();assert.equal(calls,2);assert.equal(h.statuses[0].status,'completed');assert.equal(h.errors.at(-1),'');assert.equal(h.scheduled.length,1);
});
test('pending and processing continue until a terminal response',async()=>{
    const responses=['pending','processing','completed'];const h=harness(async()=>({status:responses.shift()}));
    await flush();await h.scheduled[0].fn();await h.scheduled[1].fn();assert.deepEqual(h.statuses.map(s=>s.status),['pending','processing','completed']);assert.equal(h.scheduled.length,2);
});
test('failed job stops polling and preserves partial counts',async()=>{
    const h=harness(async()=>({status:'failed',processedRows:1,totalRows:2}));await flush();assert.equal(h.scheduled.length,0);assert.equal(h.statuses[0].processedRows,1);
});
test('expired session or forbidden access stops with actionable message',async()=>{
    for(const status of [401,403,419]){const h=harness(async()=>{throw {response:{status}};});await flush();assert.equal(h.scheduled.length,0);assert.match(h.errors[0],/Sign in again/);}
});
test('closing dialog ignores outstanding response',async()=>{
    let resolve;const h=harness(()=>new Promise(r=>{resolve=r;}));h.stop();resolve({status:'completed'});await flush();assert.equal(h.statuses.length,0);assert.equal(h.scheduled.length,0);
});
test('retry delay stays bounded and cancellation stops scheduled retries',async()=>{
    const h=harness(async()=>{throw new Error('offline');});await flush();for(let i=0;i<5;i++)await h.scheduled[i].fn();assert.ok(h.scheduled.every(s=>s.delay<=15000));h.stop();await h.scheduled.at(-1).fn();assert.equal(h.scheduled.length,6);
});

test('HTML or malformed response cannot silently strand the progress indicator',async()=>{
    const h=harness(async()=>'<html>sign in</html>');await flush();assert.equal(h.statuses.length,0);assert.match(h.errors[0],/Retrying/);assert.equal(h.scheduled.length,1);
});
