import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';

test('real dashboard and modal trees tolerate partial dates and preserve role controls', async () => {
const app=process.cwd();
const src=`import React from 'react';import {renderToStaticMarkup} from 'react-dom/server';import Office from './resources/js/Pages/Admin/OfficeDashboard.jsx';import Admin from './resources/js/Pages/Admin/Dashboard.jsx';import Manage from './resources/js/Pages/Admin/Partials/ManagePeriodsModal.jsx';globalThis.route=(name)=>name?'/'+name:{current:()=>false};
const partial={id:1,semester:'1st Semester',academic_year:'2026-2027',is_active:true};const full={...partial,start_date:'2026-08-01',end_date:'2026-12-31'};
const summary={responses:0,expected:1,pending:1,rating:0,offices:1,progress:0,status:'No Data',officeName:'Synthetic Office',term:'1st Semester'};
const render=(Component,props,role)=>{globalThis.testRole=role;return renderToStaticMarkup(React.createElement(Component,props));};
for(const [periods,isReviewOpen] of [[[partial],true],[[full],false],[[],false]]){const html=render(Office,{summary,periods,isReviewOpen},'office_admin');if(!html.includes('Review Period')||!html.includes('Evaluation Feed'))throw Error('Dashboard incomplete');if(!html.includes(isReviewOpen?'Evaluation is open':'Evaluation is closed'))throw Error('Status mismatch');if(html.includes('Manage Periods')||html.includes('Close Evaluations')||html.includes('Open Evaluations')||html.includes('Student Feedback'))throw Error('Office controls/privacy regression');if(periods.length&&(!html.includes(partial.semester)||!html.includes(partial.academic_year)))throw Error('Missing real period label');}
for(const role of ['super_admin','department_admin']){const html=render(Admin,{periods:[full],isReviewOpen:true,canManagePeriods:role==='super_admin',showOffices:role==='super_admin'},role);if(!html.includes('Review Period'))throw Error('Admin dashboard missing');if((role==='super_admin')!==html.includes('Manage Periods'))throw Error('Management privilege changed');}
render(Manage,{show:false,periods:[partial],onClose:()=>{}},'super_admin');render(Manage,{show:true,periods:[partial],onClose:()=>{}},'super_admin');render(Manage,{show:true,periods:[full],onClose:()=>{}},'super_admin');
console.log('PASS: 8 real dashboard/modal render scenarios, including missing dates, Office Open/Closed/no-period, Super Admin and Department Admin. No dashboard, ReviewPeriodCard, ManagePeriodsModal, Modal, feed, layout or Headless UI component was stubbed.');`;
const out=await build({stdin:{resolveDir:app,contents:src},bundle:true,platform:'node',format:'cjs',jsx:'automatic',write:false,loader:{'.png':'dataurl'},plugins:[{name:'inertia-context-adapter-only',setup(b){b.onResolve({filter:/^@inertiajs\/react$/},()=>({path:'inertia',namespace:'stub'}));b.onLoad({filter:/.*/,namespace:'stub'},()=>({resolveDir:app,contents:"import React from 'react';export const router={};export const Head=()=>null;export const Link=({children,...props})=>React.createElement('a',props,children);export const usePage=()=>({props:{auth:{user:{role:globalThis.testRole||'office_admin'}}}});export const useForm=(data)=>({data,errors:{},processing:false,setData(){},post(){},reset(){}});"}));b.onResolve({filter:/^@\//},args=>{const base=app+'/resources/js/'+args.path.slice(2);return{path:fs.existsSync(base)?base:base+'.jsx'};});}}]});
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'ggc-dashboard-render-'));
const file=path.join(dir,'render.cjs');fs.writeFileSync(file,out.outputFiles[0].text);
const output=execFileSync(process.execPath,[file],{encoding:'utf8'});assert.match(output,/PASS: 8 real dashboard/);
});
