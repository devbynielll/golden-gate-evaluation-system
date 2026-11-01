import { test } from 'node:test';
import assert from 'node:assert/strict';
import { loadReportLogo } from '../../resources/js/Pages/Admin/Reports/Partials/reportLogo.js';
import { readFileSync } from 'node:fs';
test('logo is drawn over opaque white before JPEG encoding at native dimensions',async()=>{
 const operations=[];const ctx={fillRect(...v){operations.push(['fill',this.fillStyle,...v]);},drawImage(){operations.push(['draw']);}};
 const canvas={getContext:()=>ctx,toDataURL:(...args)=>{operations.push(args);return 'data:image/jpeg;base64,synthetic';}};
 class MockImage { naturalWidth=750;naturalHeight=750;set src(v){this.onload();} }
 assert.match(await loadReportLogo('synthetic',{ImageClass:MockImage,createCanvas:()=>canvas}),/^data:image\/jpeg/);
 assert.deepEqual(operations,[['fill','#ffffff',0,0,750,750],['draw'],['image/jpeg',1]]);assert.equal(canvas.width,750);assert.equal(canvas.height,750);
});
test('logo loading errors reject instead of hanging report export',async()=>{
 class Failed {set src(v){this.onerror();}}
 await assert.rejects(loadReportLogo('synthetic',{ImageClass:Failed}),/Could not load/);
});
test('Office dashboard removes only its feedback presentation',()=>{
 const src=readFileSync(new URL('../../resources/js/Pages/Admin/OfficeDashboard.jsx',import.meta.url),'utf8');
 assert.ok(!src.includes('Student Feedback'));assert.ok(!src.includes('summary.feedback'));assert.ok(src.includes('summary.responses'));assert.ok(src.includes('ProgressBarCard'));assert.ok(src.includes("route('reports.office')"));
});
