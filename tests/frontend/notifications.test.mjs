import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {build} from 'esbuild';
const root=process.cwd();
const output=await build({stdin:{resolveDir:root,contents:`export {default as Notifications} from './resources/js/Components/EvaluationNotifications.jsx';`},bundle:true,platform:'node',format:'cjs',jsx:'automatic',write:false,plugins:[{name:'notification-adapters',setup(b){
 b.onResolve({filter:/^react$/},a=>a.importer.endsWith('/EvaluationNotifications.jsx')?{path:'hooks',namespace:'test'}:undefined);
 b.onResolve({filter:/^axios$/},()=>({path:'axios',namespace:'test'}));
 b.onResolve({filter:/^@inertiajs\/react$/},()=>({path:'inertia',namespace:'test'}));
 b.onResolve({filter:/^@headlessui\/react$/},()=>({path:'headless',namespace:'test'}));
 b.onResolve({filter:/^\.\/Modal$/},()=>({path:'modal',namespace:'test'}));
 b.onLoad({filter:/.*/,namespace:'test'},a=>({resolveDir:root,contents:{hooks:'export const useState=(...a)=>globalThis.noticeHarness.useState(...a);export const useRef=(...a)=>globalThis.noticeHarness.useRef(...a);export const useEffect=(...a)=>globalThis.noticeHarness.useEffect(...a);',axios:'export default {get:(...a)=>globalThis.noticeGet(...a),patch:(...a)=>globalThis.noticePatch(...a)};',inertia:'export const router={visit:(...a)=>globalThis.noticeVisits.push(a)};',headless:'export const DialogTitle="h2";',modal:'export default "modal";'}[a.path]}));
}}]});
const mod={exports:{}};new Function('require','module','exports',output.outputFiles[0].text)(createRequire(import.meta.url),mod,mod.exports);const{Notifications}=mod.exports;
function nodes(t,p){if(!t||typeof t!=='object')return[];if(Array.isArray(t))return t.flatMap(v=>nodes(v,p));return[...(p(t)?[t]:[]),...nodes(t.props?.children,p)];}
function harness(){const state=[],effects=[];let cursor=0,pending=[];const h={useState(initial){const i=cursor++;if(!(i in state))state[i]=initial;return[state[i],v=>state[i]=typeof v==='function'?v(state[i]):v];},useRef(initial){const[ref]=h.useState({current:initial});return ref;},useEffect(fn,deps){const i=cursor++;if(!effects[i])pending.push(()=>effects[i]={cleanup:fn()});},render(){cursor=0;pending=[];globalThis.noticeHarness=h;const t=Notifications();pending.forEach(f=>f());return t;},cleanup(){effects.forEach(e=>e?.cleanup?.());}};return h;}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const item={id:'opaque-id',kind:'office',read:false,title:'Office evaluation activity',subject:'REG — Synthetic Registrar',period:'1st Semester | 2026-2027',message:'Aggregated activity for an officially closed period.',url:'/admin/reports/office?period_id=1'};
function setup(){globalThis.route=(name,id)=>'/'+name+(id?'/'+id:'');globalThis.noticeVisits=[];globalThis.document={hidden:false,addEventListener(){},removeEventListener(){}};globalThis.window={setInterval(fn){globalThis.noticeTimer=fn;return 1;},clearInterval(){},addEventListener(){},removeEventListener(){}};globalThis.noticeGet=async()=>({data:{unread:1,items:[item],page:1,lastPage:1}});globalThis.noticePatch=async()=>({data:{ok:true}});}

test('notification bell, loading, empty/error states and dialog controls are accessible',async()=>{
 setup();let resolve;noticeGet=()=>new Promise(r=>resolve=r);const h=harness();let t=h.render();assert.equal(nodes(t,n=>n.props?.role==='status')[0].props.children,'Loading notifications…');
 const bell=nodes(t,n=>n.props?.['aria-haspopup']==='dialog')[0];assert.equal(bell.props['aria-expanded'],false);bell.props.onClick();t=h.render();assert.equal(nodes(t,n=>n.type==='modal')[0].props.show,true);
 resolve({data:{unread:0,items:[],page:1,lastPage:1}});await tick();t=h.render();assert.ok(nodes(t,n=>n.props?.children==='No released notifications are available.').length);
 noticeGet=async()=>{throw new Error('network');};await nodes(t,n=>n.props?.children==='Refresh notifications')[0].props.onClick();t=h.render();assert.ok(nodes(t,n=>n.props?.role==='alert').length);assert.equal(nodes(t,n=>n.type==='li').length,0);
 nodes(t,n=>n.props?.['aria-label']==='Close notifications')[0].props.onClick();assert.equal(nodes(h.render(),n=>n.type==='modal')[0].props.show,false);h.cleanup();
});

test('read/unread controls use scoped API and report navigation requires a successful authorization recheck',async()=>{
 setup();const calls=[];noticePatch=async(url,data,opts)=>{calls.push({url,data,opts});return{data:{ok:true}};};const h=harness();h.render();await tick();let t=h.render();assert.match(nodes(t,n=>n.props?.['aria-haspopup']==='dialog')[0].props['aria-label'],/1 unread/);
 await nodes(t,n=>n.props?.['aria-label']?.startsWith('Mark REG'))[0].props.onClick();assert.equal(calls[0].url,'/admin-notifications.update/opaque-id');assert.deepEqual(calls[0].data,{read:true});
 t=h.render();await nodes(t,n=>n.props?.children?.includes?.('Office')&&n.type==='button')[0].props.onClick();assert.deepEqual(noticeVisits,[[item.url]]);
 noticePatch=async()=>{throw{response:{status:404}};};t=h.render();await nodes(t,n=>n.props?.['aria-label']?.startsWith('Mark REG'))[0].props.onClick();t=h.render();assert.equal(nodes(t,n=>n.type==='li').length,0);assert.equal(noticeVisits.length,1);assert.ok(nodes(t,n=>n.props?.role==='alert').length);h.cleanup();
});

test('revalidation removes reopened or revoked groups and ignores late responses after unmount',async()=>{
 setup();const h=harness();h.render();await tick();assert.equal(nodes(h.render(),n=>n.type==='li').length,1);
 noticeGet=async()=>({data:{unread:0,items:[],page:1,lastPage:1}});await noticeTimer();assert.equal(nodes(h.render(),n=>n.type==='li').length,0);
 let resolve;noticeGet=()=>new Promise(r=>resolve=r);const pending=noticeTimer();h.cleanup();resolve({data:{unread:1,items:[item],page:1,lastPage:1}});await pending;assert.equal(nodes(h.render(),n=>n.type==='li').length,0);
});

test('notification guidance explains approved delayed release without suggesting a per-student alert',async()=>{
 setup();noticeGet=async()=>({data:{unread:0,items:[],page:1,lastPage:1}});const h=harness();h.render();await tick();const t=h.render();
 const paragraphs=nodes(t,n=>n.type==='p'&&typeof n.props?.children==='string').map(n=>n.props.children).join(' ');
 assert.match(paragraphs,/delayed aggregate notices, not instant alerts/);
 assert.match(paragraphs,/official review period closure and at least five distinct students/);
 assert.match(paragraphs,/Open periods and groups below five distinct students are not listed/);
 assert.match(paragraphs,/No submission times or student details/);h.cleanup();
});
