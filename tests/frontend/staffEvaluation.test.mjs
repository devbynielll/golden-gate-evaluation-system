import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {build} from 'esbuild';
const root=process.cwd();
const output=await build({stdin:{resolveDir:root,contents:`export {default as Page} from './resources/js/Pages/Student/Evaluation.jsx';export {default as Form} from './resources/js/Pages/Student/Partials/EvaluationFormModal.jsx';export {default as Card} from './resources/js/Pages/Student/Partials/EvaluationCard.jsx';`},bundle:true,platform:'node',format:'cjs',jsx:'automatic',write:false,loader:{'.png':'dataurl'},plugins:[{name:'student-adapters',setup(b){
 b.onResolve({filter:/^react$/},a=>/\/(Evaluation|EvaluationFormModal)\.jsx$/.test(a.importer)?{path:'hooks',namespace:'test'}:undefined);
 b.onResolve({filter:/^@inertiajs\/react$/},()=>({path:'inertia',namespace:'test'}));
 b.onResolve({filter:/^@headlessui\/react$/},()=>({path:'headless',namespace:'test'}));
 b.onResolve({filter:/^@\/Layouts\//},()=>({path:'layout',namespace:'test'}));
 b.onLoad({filter:/.*/,namespace:'test'},a=>({resolveDir:root,contents:{hooks:'export const useState=(...a)=>globalThis.studentHarness.useState(...a);export const useMemo=(fn)=>fn();',inertia:'export const Head="head";export const router={get:(...a)=>globalThis.studentCalls.push(a),post:(...a)=>globalThis.studentCalls.push(a)};',headless:'export const Dialog="dialog",DialogPanel="panel",DialogTitle="h2",Transition="transition",TransitionChild="transitionChild";',layout:'export default "layout";'}[a.path]}));
 b.onResolve({filter:/^@\//},a=>{const p=path.join(root,'resources/js',a.path.slice(2));return{path:fs.existsSync(p)?p:p+'.jsx'};});
}}]});
const mod={exports:{}};new Function('require','module','exports',output.outputFiles[0].text)(createRequire(import.meta.url),mod,mod.exports);const{Page,Form,Card}=mod.exports;
function harness(Component,props){const states=[];let index=0;const h={useState(initial){const i=index++;if(!(i in states))states[i]=initial;return[states[i],v=>states[i]=typeof v==='function'?v(states[i]):v];},render(){index=0;globalThis.studentHarness=h;return Component(props);}};return h;}
function nodes(t,p){if(!t||typeof t!=='object')return[];if(Array.isArray(t))return t.flatMap(v=>nodes(v,p));return[...(p(t)?[t]:[]),...nodes(t.props?.children,p)];}
const categories=[{id:1,name:'Stored criteria',section:'1',questions:[{id:11,text:'Staff courtesy'}]}];
globalThis.route=(name,id)=>`/${name}/${id??''}`;

test('optional staff workflow keeps required progress and posts only to the staff endpoint',()=>{
 globalThis.studentCalls=[];const props={progress:{completed:2,total:3},isSubmissionOpen:true,officeStaff:{data:[{id:8,name:'Synthetic Staff',subtitle:'Assistant | Registrar',completed:false,categories}],meta:{current_page:1,last_page:2}},staffProgress:{completed:0,total:1}};
 const h=harness(Page,props);let t=h.render();assert.ok(nodes(t,n=>n.props?.completed===2&&n.props?.total===3).length);
 const card=nodes(t,n=>n.props?.title==='Synthetic Staff')[0];assert.equal(card.props.disabled,false);card.props.onClick();t=h.render();
 const modal=nodes(t,n=>n.props?.onSubmit&&n.props?.categories)[0];const success=()=>{},error=()=>{};modal.props.onSubmit({ratings:{11:4},comments:'Synthetic'},{onSuccess:success,onError:error});
 assert.equal(studentCalls[0][0],'/evaluation.staff.store/8');assert.equal(studentCalls[0][2].onError,error);
 assert.ok(studentCalls[0][2].only.includes('staffProgress'));assert.ok(studentCalls[0][2].only.includes('progress'));
 props.isSubmissionOpen=false;assert.equal(nodes(h.render(),n=>n.props?.title==='Synthetic Staff')[0].props.disabled,true);
 props.isSubmissionOpen=true;props.officeStaff.data[0].categories=[];assert.equal(nodes(h.render(),n=>n.props?.title==='Synthetic Staff')[0].props.disabled,true);
});

test('staff form exposes radio labels and backend failures without showing false success',()=>{
 let submitted;const h=harness(Form,{show:true,title:'Synthetic Staff',categories,onClose(){},onSubmit:(data,callbacks)=>submitted={data,callbacks}});let t=h.render();
 assert.equal(nodes(t,n=>n.type==='button'&&n.props.type==='submit')[0].props.disabled,true);
 const rating=nodes(t,n=>n.type==='input'&&n.props.type==='radio')[3];assert.match(rating.props['aria-label'],/Staff courtesy: 4/);rating.props.onChange();t=h.render();
 nodes(t,n=>n.type==='form')[0].props.onSubmit({preventDefault(){}});assert.deepEqual(submitted.data.ratings,{11:4});
 t=h.render();assert.equal(nodes(t,n=>n.type==='button'&&n.props.type==='submit')[0].props.disabled,true);
 submitted.callbacks.onError({staff:'Evaluations are closed right now.'});t=h.render();assert.equal(nodes(t,n=>n.props?.role==='alert')[0].props.children,'Evaluations are closed right now.');
 assert.equal(nodes(t,n=>n.props?.children==='Thank You!').length,0);
 nodes(t,n=>n.type==='form')[0].props.onSubmit({preventDefault(){}});submitted.callbacks.onSuccess();t=h.render();assert.equal(nodes(t,n=>n.props?.children==='Thank You!').length,1);
});

test('disabled staff cards block mouse and keyboard button activation',()=>{
 let calls=0;const t=Card({title:'Synthetic',disabled:true,disabledLabel:'Evaluations closed',onClick(){calls++;}});const button=nodes(t,n=>n.type==='button')[0];assert.equal(button.props.disabled,true);assert.equal(button.props.onClick,undefined);assert.equal(calls,0);
});
