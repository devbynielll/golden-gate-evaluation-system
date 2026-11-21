import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {build} from 'esbuild';
const app=process.cwd();
const output=await build({stdin:{resolveDir:app,contents:`export {default as useReport} from './resources/js/Pages/Admin/Reports/Partials/useReport.js';export {default as Viewer} from './resources/js/Pages/Admin/Reports/Partials/ReportViewer.jsx';export {default as Faculty} from './resources/js/Pages/Admin/Reports/Partials/FacultyPerformanceCard.jsx';export {default as Office} from './resources/js/Pages/Admin/Reports/Partials/OfficePerformanceCard.jsx';export {createReportDocument} from './resources/js/Pages/Admin/Reports/Partials/downloadReport.js';export {renderToStaticMarkup} from 'react-dom/server';export {createElement} from 'react';`},bundle:true,platform:'node',format:'cjs',jsx:'automatic',write:false,loader:{'.png':'dataurl'},plugins:[{name:'focused-report-adapters',setup(b){
 b.onResolve({filter:/^react$/},a=>/\/(useReport\.js|FacultyPerformanceCard\.jsx|OfficePerformanceCard\.jsx)$/.test(a.importer)?{path:'hooks',namespace:'test'}:undefined);
 b.onResolve({filter:/^axios$/},()=>({path:'axios',namespace:'test'}));
 b.onResolve({filter:/^\.\/downloadReport$/},a=>a.importer.endsWith('/useReport.js')?{path:'download',namespace:'test'}:undefined);
 b.onResolve({filter:/^\.\/reportLogo$/},()=>({path:'logo',namespace:'test'}));
 b.onResolve({filter:/^jspdf$/},()=>({path:'pdf',namespace:'test'}));
 b.onResolve({filter:/^@\/Components\/Modal$/},()=>({path:'modal',namespace:'test'}));
 b.onResolve({filter:/^@headlessui\/react$/},a=>a.importer.endsWith('/ReportViewer.jsx')?{path:'title',namespace:'test'}:undefined);
 b.onResolve({filter:/^@inertiajs\/react$/},()=>({path:'inertia',namespace:'test'}));
 b.onLoad({filter:/.*/,namespace:'test'},a=>({resolveDir:app,contents:{
 hooks:'export const useState=(...a)=>globalThis.reportHarness.useState(...a);export const useRef=(...a)=>globalThis.reportHarness.useRef(...a);export const useEffect=(...a)=>globalThis.reportHarness.useEffect(...a);',
 axios:'export default {get:(...a)=>globalThis.reportGet(...a)};',
 download:'export default async (...a)=>globalThis.reportDownload(...a);',
 logo:'export const loadReportLogo=async()=>"synthetic";',
 pdf:`import{jsPDF as Native}from'${app}/node_modules/jspdf/dist/jspdf.node.min.js';export class jsPDF extends Native{constructor(...args){super(...args);this.addImage=()=>this;this.textLayout=[];const original=this.text;this.text=(...a)=>{this.textLayout.push({text:Array.isArray(a[0])?[...a[0]]:a[0],x:a[1],y:a[2],align:a[3]?.align??'left',page:this.internal.getCurrentPageInfo().pageNumber});return original.apply(this,a);};}}`,
 modal:'import React from "react";export default function Modal({show,children}){return show?React.createElement("section",{},children):null;}',
 title:'import React from "react";export const DialogTitle=({children,...props})=>React.createElement("h2",props,children);',
 inertia:'export const router={get:(...args)=>globalThis.reportVisits.push(args)};',
 }[a.path]}));
 b.onResolve({filter:/^@\//},a=>{const p=path.join(app,'resources/js',a.path.slice(2));return{path:fs.existsSync(p)?p:p+'.jsx'};});
}}]});
const compiled={exports:{}};new Function('require','module','exports',output.outputFiles[0].text)(createRequire(import.meta.url),compiled,compiled.exports);
const {useReport,Viewer,Faculty,Office,createReportDocument,renderToStaticMarkup,createElement}=compiled.exports;
function harness(component,props){const states=[],effects=[];let cursor=0,pending=[];const h={
 useState(initial){const index=cursor++;if(!(index in states))states[index]=initial;return[states[index],v=>{states[index]=typeof v==='function'?v(states[index]):v;}];},
 useRef(initial){const [ref]=h.useState({current:initial});return ref;},
 useEffect(fn,deps){const index=cursor++;if(!effects[index]||deps.some((d,i)=>d!==effects[index].deps[i]))pending.push(()=>{effects[index]?.cleanup?.();effects[index]={deps,cleanup:fn()};});},
 render(){cursor=0;pending=[];globalThis.reportHarness=h;const tree=component(props);pending.forEach(f=>f());return tree;},
};return h;}
function nodes(tree,predicate){if(!tree||typeof tree!=='object')return[];if(Array.isArray(tree))return tree.flatMap(n=>nodes(n,predicate));return[...(predicate(tree)?[tree]:[]),...nodes(tree.props?.children,predicate)];}
const detail={id:1,name:'Synthetic Faculty',facultyId:'F001',department:'D1',rating:2.75,responses:2,status:'Satisfactory',feedback:'Synthetic combined feedback',sections:[{id:1,label:'Section 1: Teaching',rating:3.33,answeredItems:3},{id:2,label:'Section 2: Support',rating:1,answeredItems:1}]};
const report={subject:'Faculty',reviewPeriod:'1st Semester | 2026-2027',filterLabel:'Department: D1',detail};
globalThis.route=n=>'/'+n;globalThis.reportVisits=[];

test('cards use applied filters for complete previews/downloads despite unsaved controls',async()=>{
 for(const [Component,subject,selected] of [[Faculty,'Faculty',{department_id:1,period_id:7}],[Office,'Office',{search:'Applied',period_id:7}]]){
  const calls=[],downloads=[];const payload={subject,reviewPeriod:'Applied semester',filterLabel:'Applied filter',summaryRows:Array.from({length:13},(_,i)=>({...detail,id:i+1,officeCode:'O'+i}))};
  globalThis.reportGet=async(url,options)=>{calls.push({url,...options});return{data:payload};};globalThis.reportDownload=async value=>downloads.push(value);
  const h=harness(Component,{selected});let tree=h.render();
  nodes(tree,n=>n.props?.onPeriodChange)[0].props.onPeriodChange(9);
  if(subject==='Faculty')nodes(tree,n=>n.type==='select')[0].props.onChange({target:{value:2}});
  else nodes(tree,n=>n.type==='input')[0].props.onChange({target:{value:'Unsaved'}});
  tree=h.render();await nodes(tree,n=>n.props?.children==='View Summary')[0].props.onClick();
  assert.deepEqual(calls[0].params,{...selected,report:'summary',target_id:undefined});
  tree=h.render();const viewer=nodes(tree,n=>n.props?.onDownload)[0];assert.equal(viewer.props.report,payload);
  await viewer.props.onDownload();assert.equal(downloads[0],payload);
  await nodes(tree,n=>Array.isArray(n.props?.children)&&n.props.children.includes('Generate Full Report'))[0].props.onClick();
  assert.equal(downloads[1].summaryRows.length,13);assert.deepEqual(calls[1].params,calls[0].params);
 }
});

test('request cancellation, late responses and permission errors cannot populate a stale viewer',async()=>{
 let resolveOld;globalThis.reportGet=()=>new Promise(resolve=>{resolveOld=resolve;});globalThis.reportDownload=async()=>{};
 const selected={period_id:1};const h=harness(()=>useReport('reports.faculty',selected));let control=h.render();const old=control.open(1);control=h.render();assert.equal(control.loading,true);
 control.close();resolveOld({data:report});await old;control=h.render();assert.equal(control.show,false);assert.equal(control.report,null);
 globalThis.reportGet=async()=>{throw{response:{status:403}};};await control.open(1);control=h.render();assert.equal(control.loading,false);assert.match(control.error,/access has changed/);assert.equal(control.report,null);
 globalThis.reportGet=async()=>({data:report});await control.open(1);control=h.render();assert.equal(control.report,report);
 selected.period_id=2;h.render();control=h.render();assert.equal(control.show,false);assert.equal(control.report,null);
});

test('viewer renders accessible loading/error/empty states, identities and actual section scores',()=>{
 const html=props=>renderToStaticMarkup(createElement(Viewer,{show:true,onClose(){},onDownload(){},...props}));
 assert.match(html({loading:true}),/role="status"/);assert.match(html({error:'Access denied'}),/role="alert"/);
 assert.match(html({report:{...report,detail:undefined,summaryRows:[]}}),/No records match/);
 const rendered=html({report});for(const text of ['F001','Department: D1','2.75','Section 1: Teaching','3.33','Synthetic combined feedback'])assert.ok(rendered.includes(text));
 assert.match(rendered,/scope="col"/);assert.match(rendered,/aria-label="Close report"/);
 const empty=html({report:{...report,detail:{...detail,status:'No Data',rating:0,sections:[],feedback:''}}});assert.ok(empty.includes('No rated sections'));assert.ok(!empty.includes('0.00'));
});

test('real PDF text uses the same precision, section labels and identifiers and spans all rows/pages',async()=>{
 const doc=await createReportDocument(report);const text=doc.output();
 for(const value of ['F001','D1','2.75','Teaching','3.33','Support','1.00','Synthetic combined feedback'])assert.ok(text.includes(value),value);
 const summary={subject:'Office',reviewPeriod:'2nd Semester | 2026-2027',filterLabel:'All Offices',summaryRows:Array.from({length:65},(_,i)=>({id:i,name:'Synthetic Office '+(i+1),officeCode:'OFFICE-'+(i+1),rating:3.75,status:'Very Satisfactory',responses:2}))};
 const full=await createReportDocument(summary);assert.ok(full.getNumberOfPages()>1);assert.ok(full.output().includes('OFFICE-65'));assert.ok(full.output().includes('3.75'));
 fs.mkdirSync('node_modules/.cache/ggc-report-check',{recursive:true});fs.writeFileSync('node_modules/.cache/ggc-report-check/individual-test.pdf',Buffer.from(doc.output('arraybuffer')));fs.writeFileSync('node_modules/.cache/ggc-report-check/summary-test.pdf',Buffer.from(full.output('arraybuffer')));
});

test('staff viewers/PDFs suppress counts, scores and feedback and retain evaluated metadata after release',async()=>{
 const withheld={id:'a'.repeat(64),name:'Synthetic Staff',position:'Records Assistant',officeCode:'REG',officeName:'Registrar',reviewPeriod:'1st Semester | 2026-2027',rating:null,responses:null,status:'Withheld',sections:[],disclosureMessage:'Insufficient responses to protect student anonymity.',feedback:'SECRET must never appear'};
 const staffReport={subject:'Staff',reviewPeriod:withheld.reviewPeriod,filterLabel:'Office: REG',detail:withheld};
 const html=renderToStaticMarkup(createElement(Viewer,{show:true,report:staffReport,onClose(){},onDownload(){}}));
 for(const text of ['Records Assistant','Registrar','Withheld',withheld.disclosureMessage])assert.ok(html.includes(text));
 assert.ok(!html.includes('SECRET'));assert.ok(!html.includes('Student Feedback'));assert.ok(!html.includes('0.00'));
 const hidden=await createReportDocument(staffReport);const text=hidden.output();
 assert.ok(text.includes('Insufficient responses'));assert.ok(text.includes('Withheld'));assert.ok(!text.includes('SECRET'));assert.ok(!text.includes('Student Feedback'));
 const released={...staffReport,detail:{...withheld,position:'Records Assistant / Senior Officer',rating:3,responses:5,status:'Satisfactory',sections:[{label:'Section 1: Stored Staff Category',rating:3,answeredItems:5}],disclosureMessage:''}};
 const pdf=await createReportDocument(released);for(const value of ['Senior Officer','Stored Staff Category','3.00','Responses: 5'])assert.ok(pdf.output().includes(value),value);
 assert.ok(!pdf.output().includes('SECRET'));
 const summary=await createReportDocument({...staffReport,detail:undefined,summaryRows:[withheld]});assert.ok(summary.output().includes('Insufficient responses'));assert.ok(summary.output().includes('Withheld'));
 fs.mkdirSync('node_modules/.cache/ggc-phase4-check',{recursive:true});
 for(const [name,doc]of[['staff-withheld',hidden],['staff-released',pdf],['staff-summary',summary]])fs.writeFileSync(`node_modules/.cache/ggc-phase4-check/${name}.pdf`,Buffer.from(doc.output('arraybuffer')));
});

test('staff downloads recheck the live release state instead of exporting a stale preview',async()=>{
 const filters={office_id:2,period_id:3,search:'Applied'};const h=harness(()=>useReport('reports.staff',filters));
 let calls=0;const released={subject:'Staff',detail:{rating:3,responses:5}};const hidden={subject:'Staff',detail:{rating:null,responses:null}};
 globalThis.reportGet=async(url,options)=>{calls++;assert.deepEqual(options.params,{...filters,report:'individual',target_id:'a'.repeat(64)});return{data:calls===1?released:hidden};};
 const downloads=[];globalThis.reportDownload=async data=>downloads.push(data);
 let control=h.render();await control.open('a'.repeat(64));control=h.render();assert.equal(control.report,released);
 await control.downloadCurrent();assert.equal(calls,2);assert.equal(downloads[0],hidden);
});

test('all six PDF modes center only the applied period/filter and identity information',async()=>{
 for(const subject of ['Faculty','Office','Staff'])for(const individual of [false,true]){
  const row={...detail,name:`Synthetic ${subject}`,officeCode:'REG',officeName:'Synthetic Registrar',position:'Records Assistant',reviewPeriod:report.reviewPeriod,responses:5};
  const data={subject,reviewPeriod:report.reviewPeriod,filterLabel:'Applied Department / Office: Synthetic',...(individual?{detail:row}:{summaryRows:[row]})};
  const doc=await createReportDocument(data);const center=doc.internal.pageSize.getWidth()/2;
  const find=value=>doc.textLayout.find(entry=>[].concat(entry.text).join(' ').includes(value));
  for(const value of [data.reviewPeriod,data.filterLabel,...(individual?[`${subject} Name:`,subject==='Faculty'?'Faculty ID:':subject==='Office'?'Office Code:':'Evaluated position:']:[])]){
   const entry=find(value);assert.ok(entry,value);assert.equal(entry.align,'center');assert.ok(Math.abs(entry.x-center)<0.001);
  }
  assert.equal(find('Evaluation '+(individual?'Individual':'Performance Summary')+' Report').align,'left');
  if(individual){assert.equal(find('Overall Rating:').align,'left');assert.equal(find('Responses:').align,'left');}
  const label=individual?'Criteria Section':subject==='Faculty'?'Faculty Name':subject==='Office'?'Office Name':'Staff Name';
  assert.equal(find(label).align,'left');
 }
});

test('long PDF identity blocks wrap and paginate inside printable margins without losing content',async()=>{
 const name=Array.from({length:90},(_,i)=>`Historical staff name ${i+1} with a sufficiently long descriptive label`).join(' / ');
 const data={subject:'Staff',reviewPeriod:report.reviewPeriod,filterLabel:'Office: Synthetic Registrar',detail:{...detail,name,position:'Records Assistant',officeCode:'REG',officeName:'Synthetic Registrar',responses:5,feedback:undefined}};
 const doc=await createReportDocument(data);assert.ok(doc.getNumberOfPages()>1);
 const info=doc.textLayout.filter(entry=>entry.align==='center');assert.ok(info.some(entry=>Array.isArray(entry.text)&&entry.text.length>1));
 for(const entry of info){assert.ok(entry.y>=20);for(const [index,line]of[].concat(entry.text).entries()){assert.ok(entry.y+index*5<=260);assert.ok(doc.getTextWidth(line)<=181.1);}}
 assert.ok(info.flatMap(entry=>[].concat(entry.text)).join(' ').includes('Historical staff name 90'));
 assert.ok(doc.output().includes('3.33'));assert.ok(!doc.output().includes('Student Feedback'));
});
