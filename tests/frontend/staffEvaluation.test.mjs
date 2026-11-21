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

test('shared Faculty Office and Staff modal limits optional comments to 300 characters',()=>{
 let saved;
 const props={show:true,title:'Synthetic Evaluation',categories,onClose(){},onSubmit:data=>saved=data};
 const h=harness(Form,props);let t=h.render();
 const textarea=nodes(t,n=>n.type==='textarea')[0];
 assert.equal(textarea.props.maxLength,300);
 const order=nodes(t,n=>n.type==='table'||n.type==='textarea'||(n.type==='button'&&n.props.type==='submit'));
 assert.equal(order[order.length-2].type,'textarea');assert.equal(order[order.length-1].props.type,'submit');
 assert.match(textarea.props['aria-label'],/Optional comments or feedback/i);
 const countText=tree=>[].concat(nodes(tree,n=>n.props?.['aria-live']==='polite')[0].props.children).join('');
 assert.equal(countText(t),'0 / 300 characters');
 textarea.props.onChange({target:{value:'Feedback'}});t=h.render();assert.equal(countText(t),'8 / 300 characters');
 textarea.props.onChange({target:{value:'a'.repeat(300)}});t=h.render();
 assert.equal(countText(t),'300 / 300 characters');
 nodes(t,n=>n.type==='input'&&n.props.type==='radio')[3].props.onChange();
 t=h.render();nodes(t,n=>n.type==='form')[0].props.onSubmit({preventDefault(){}});
 assert.equal(saved.comments,'a'.repeat(300));assert.deepEqual(saved.ratings,{11:4});
 saved=undefined;const blank=harness(Form,props);t=blank.render();
 nodes(t,n=>n.type==='input'&&n.props.type==='radio')[0].props.onChange();
 t=blank.render();nodes(t,n=>n.type==='form')[0].props.onSubmit({preventDefault(){}});
 assert.equal(saved.comments,'');
});

test('Faculty Office and Staff cards close together, retain Completed labels and reopen normally',()=>{
 globalThis.studentCalls=[];
 const page={data:[{id:1,name:'Synthetic target',cardTitle:'Synthetic Faculty',categories,completed:false}],meta:{current_page:1,last_page:2}};
 const props={faculties:page,offices:page,officeStaff:page,isSubmissionOpen:false};
 const h=harness(Page,props);let t=h.render();let cards=nodes(t,n=>n.type===Card);assert.equal(cards.length,3);
 for(const card of cards){assert.equal(card.props.disabled,true);assert.match(card.props.disabledLabel,/evaluations closed/i);const native=Card(card.props);assert.equal(native.props.disabled,true);assert.equal(native.props.onClick,undefined);}
 props.isSubmissionOpen=true;t=h.render();cards=nodes(t,n=>n.type===Card);for(const card of cards)assert.equal(card.props.disabled,false);
 cards[0].props.onClick();props.isSubmissionOpen=false;t=h.render();const modal=nodes(t,n=>n.type===Form)[0];assert.equal(modal.props.isSubmissionOpen,false);
 let success=false,error;modal.props.onSubmit({ratings:{11:4},comments:''},{onSuccess(){success=true;},onError(e){error=e;}});
 assert.equal(success,false);assert.match(error.evaluation,/closed/i);assert.equal(studentCalls.length,0);
 for(const card of nodes(t,n=>n.type===Card)){const completed=Card({...card.props,completed:true});assert.equal(completed.props.onClick,undefined);assert.match(nodes(completed,n=>n.type==='span').map(n=>[].concat(n.props.children).filter(c=>typeof c==='string').join('')).join(' '),/Completed/);}
 const pagination=nodes(t,n=>n.props?.totalPages===2)[0];pagination.props.onPageChange(2);assert.ok(studentCalls[0][2].only.includes('isSubmissionOpen'));
 props.isSubmissionOpen=true;t=h.render();const reopened=nodes(t,n=>n.type===Form)[0];reopened.props.onSubmit({ratings:{11:4},comments:''},{onSuccess(){},onError(){}});assert.ok(studentCalls[1][2].only.includes('isSubmissionOpen'));
});

test('a stale open form becomes disabled and cannot show success after closure',()=>{
 let submits=0;const props={show:true,title:'Synthetic Faculty',categories,isSubmissionOpen:true,onClose(){},onSubmit(){submits++;}};
 const h=harness(Form,props);let t=h.render();nodes(t,n=>n.type==='input'&&n.props.type==='radio')[3].props.onChange();
 props.isSubmissionOpen=false;t=h.render();assert.ok(nodes(t,n=>n.props?.role==='alert').length);
 assert.ok(nodes(t,n=>n.type==='input'&&n.props.type==='radio').every(n=>n.props.disabled));assert.equal(nodes(t,n=>n.type==='textarea')[0].props.disabled,true);
 assert.equal(nodes(t,n=>n.type==='button'&&n.props.type==='submit')[0].props.disabled,true);
 nodes(t,n=>n.type==='form')[0].props.onSubmit({preventDefault(){}});t=h.render();assert.equal(submits,0);assert.equal(nodes(t,n=>n.props?.children==='Thank You!').length,0);
});
