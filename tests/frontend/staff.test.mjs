import fs from 'node:fs';import path from 'node:path';import {createRequire} from 'node:module';import {test} from 'node:test';import assert from 'node:assert/strict';import{build}from'esbuild';
const app=process.cwd();
const result=await build({stdin:{resolveDir:app,contents:`export{default as Staff}from'./resources/js/Pages/Admin/Offices/Staff.jsx';export{default as Board}from'./resources/js/Pages/Admin/Criteria/Partials/CriteriaBoard.jsx';export{default as Layout}from'./resources/js/Layouts/AdminLayout.jsx';export{default as CategoryForm}from'./resources/js/Pages/Admin/Criteria/Partials/CategoryFormModal.jsx';export{default as QuestionForm}from'./resources/js/Pages/Admin/Criteria/Partials/QuestionFormModal.jsx';`},bundle:true,platform:'node',format:'cjs',jsx:'automatic',write:false,loader:{'.png':'dataurl'},plugins:[{name:'staff-interaction-adapters',setup(b){
 b.onResolve({filter:/^react$/},a=>/\/(Staff|CriteriaBoard|CategoryFormModal|QuestionFormModal)\.jsx$/.test(a.importer)?{path:'hooks',namespace:'test'}:undefined);
 b.onResolve({filter:/^@inertiajs\/react$/},()=>({path:'inertia',namespace:'test'}));
 b.onLoad({filter:/.*/,namespace:'test'},a=>({contents:a.path==='hooks'?'export const useState=(...a)=>globalThis.staffHarness.useState(...a);export const useEffect=(...a)=>globalThis.staffHarness.useEffect(...a);':`export const Head=()=>null;export const Link=()=>null;export const usePage=()=>({props:globalThis.staffPage});export const useForm=(...a)=>globalThis.staffHarness.useForm(...a);export const router=Object.fromEntries(['get','post','put','patch','delete'].map(method=>[method,(...args)=>globalThis.staffCalls.push({method,args})]));`}));
 b.onResolve({filter:/^@\//},a=>{const p=path.join(app,'resources/js',a.path.slice(2));return{path:fs.existsSync(p)?p:p+'.jsx'};});
}}]});
const compiled={exports:{}};new Function('require','module','exports',result.outputFiles[0].text)(createRequire(import.meta.url),compiled,compiled.exports);const{Staff,Board,Layout,CategoryForm,QuestionForm}=compiled.exports;
function harness(Component,props){const values=[],effects=[];let cursor,pending=[];const h={processing:false,
 useState(initial){const i=cursor++;if(!(i in values))values[i]=initial;return[values[i],v=>{values[i]=typeof v==='function'?v(values[i]):v;}];},
 useEffect(fn,deps){const i=cursor++;if(!effects[i]||deps.some((v,j)=>v!==effects[i][j])){effects[i]=deps;pending.push(fn);}},
 useForm(initial){const[data,set]=h.useState({...initial});return{data,processing:h.processing,errors:h.errors??{},setData:(key,value)=>set(old=>typeof key==='object'?key:{...old,[key]:value}),reset:()=>set({...initial}),clearErrors(){},post:(...args)=>globalThis.staffCalls.push({method:'post',args,data}),put:(...args)=>globalThis.staffCalls.push({method:'put',args,data})};},
 render(){cursor=0;pending=[];globalThis.staffHarness=h;const out=Component(props);pending.forEach(fn=>fn());return out;}};return h;}
function nodes(tree,p){if(!tree||typeof tree!=='object')return[];if(Array.isArray(tree))return tree.flatMap(t=>nodes(t,p));return[...(p(tree)?[tree]:[]),...nodes(tree.props?.children,p)];}
function setup(role='office_admin',extra={}){globalThis.staffCalls=[];globalThis.staffPage={auth:{user:{role}},errors:{},...extra};globalThis.route=(name,args)=>name?`/${name}/${JSON.stringify(args??[])}`:{current:()=>false};}
const member={id:7,name:'Synthetic Staff',position:'Records Assistant',is_active:true};
const props={office:{id:2,code:'REG',name:'Synthetic Registrar'},staff:{data:[member],current_page:1,last_page:1},filters:{status:'active',search:''}};

test('staff form preloads identity, sends only names/positions to nested routes and respects busy/errors',()=>{
 setup();const h=harness(Staff,props);let tree=h.render();nodes(tree,n=>n.props?.['aria-label']==='Edit Synthetic Staff')[0].props.onClick();tree=h.render();
 const input=id=>nodes(tree,n=>n.props?.id===id)[0];assert.equal(input('staff-name').props.value,'Synthetic Staff');assert.equal(input('staff-position').props.value,'Records Assistant');
 input('staff-position').props.onChange({target:{value:'Senior Assistant'}});tree=h.render();
 nodes(tree,n=>n.type==='form'&&n.props['aria-busy']!==undefined)[0].props.onSubmit({preventDefault(){}});
 assert.equal(staffCalls[0].method,'put');assert.match(staffCalls[0].args[0],/offices.staff.update/);assert.deepEqual(staffCalls[0].data,{name:'Synthetic Staff',position:'Senior Assistant'});
 h.processing=true;h.errors={name:'Already registered'};tree=h.render();assert.equal(input('staff-name').props.disabled,true);assert.equal(input('staff-name').props['aria-invalid'],true);assert.ok(nodes(tree,n=>n.props?.role==='alert').length);
 const previous=staffCalls.length;nodes(tree,n=>n.type==='form'&&n.props['aria-busy']!==undefined)[0].props.onSubmit({preventDefault(){}});assert.equal(staffCalls.length,previous);
});

test('archival requires confirmation and inactive rows have no archive or delete action',()=>{
 setup();const h=harness(Staff,props);let tree=h.render();nodes(tree,n=>n.props?.['aria-label']==='Archive Synthetic Staff')[0].props.onClick();tree=h.render();const confirm=nodes(tree,n=>n.props?.title==='Archive staff member?')[0];assert.equal(confirm.props.show,true);assert.match(confirm.props.description,/Historical references/);assert.equal(staffCalls.length,0);confirm.props.onConfirm();assert.equal(staffCalls[0].method,'patch');assert.match(staffCalls[0].args[0],/offices.staff.archive/);
 tree=harness(Staff,{...props,staff:{...props.staff,data:[{...member,is_active:false}]}}).render();assert.equal(nodes(tree,n=>n.props?.['aria-label']==='Archive Synthetic Staff').length,0);assert.equal(nodes(tree,n=>n.props?.children==='Delete').length,0);
});

test('staff criteria reuse the board with separate mutation routes and shared/frozen locks',()=>{
 setup('office_admin',{selectedOfficeId:2,criteriaOfficeName:'Registrar',criteriaPermissions:{readOnlyCategoryIds:[1],usedCategoryIds:[2],usedQuestionIds:[2]}});
 const h=harness(Board,{type:'staff',label:'Office Staff',categories:{data:[{id:1,office_id:null,section_number:1,name:'Shared',questions:[{id:1,text:'Shared question'}]},{id:2,office_id:2,section_number:1,name:'Frozen',questions:[{id:2,text:'Frozen question'}]}],total:2},totalQuestions:2});let tree=h.render();
 assert.ok(nodes(tree,n=>n.props?.title==='Edit Category').every(n=>n.props.disabled));assert.ok(nodes(tree,n=>n.props?.title==='Edit Question').every(n=>n.props.disabled));
 nodes(tree,n=>Array.isArray(n.props?.children)&&n.props.children.includes('Add New Category'))[0].props.onClick();tree=h.render();nodes(tree,n=>n.props?.onSubmit&&n.props?.mode==='add')[0].props.onSubmit({name:'Local Staff',section:'2'});
 assert.match(staffCalls[0].args[0],/staff-criteria.categories.store/);assert.equal(staffCalls[0].args[1].office_id,2);
 staffCalls[0].args[2].onStart();tree=h.render();assert.ok(nodes(tree,n=>n.props?.role==='status').length);
});

test('role navigation and reused criteria forms retain explicit keyboard/screen-reader controls',()=>{
 for(const role of ['office_admin','department_admin','super_admin']){setup(role);const tree=Layout({children:null});const links=nodes(tree,n=>n.props?.href?.startsWith('/staff.index')||n.props?.href?.startsWith('/criteria.staff'));assert.equal(links.length,role==='office_admin'?2:role==='super_admin'?1:0);}
 setup();let h=harness(CategoryForm,{show:true,errors:{name:'Required'}});h.render();let tree=h.render();assert.equal(nodes(tree,n=>n.props?.id==='criteria-category-name')[0].props['aria-invalid'],true);assert.ok(nodes(tree,n=>n.props?.htmlFor==='criteria-category-name').length);
 h=harness(QuestionForm,{show:true,processing:true,errors:{text:'Required'}});h.render();tree=h.render();assert.equal(nodes(tree,n=>n.props?.['aria-label']==='Question')[0].props.disabled,true);assert.ok(nodes(tree,n=>n.props?.['aria-label']==='Close question form').length);
});
