import fs from 'node:fs';
import path from 'node:path';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';

// Exercise the real components' handlers with hook/form adapters; no browser or network.
const app = process.cwd();
const bundle = await build({
    stdin: { resolveDir: app, contents: `export {default as Login} from './resources/js/Pages/Auth/Login.jsx'; export {default as Manage} from './resources/js/Pages/Admin/Partials/ManagePeriodsModal.jsx';` },
    bundle: true, platform: 'node', format: 'cjs', jsx: 'automatic', write: false,
    loader: { '.png': 'dataurl', '.jpg': 'dataurl' },
    plugins: [{ name: 'interaction-adapters', setup(b) {
        b.onResolve({ filter: /^react$/ }, args => /\/(Login|ManagePeriodsModal)\.jsx$/.test(args.importer)
            ? { path: 'hooks', namespace: 'adapter' } : undefined);
        b.onResolve({ filter: /^@inertiajs\/react$/ }, () => ({ path: 'inertia', namespace: 'adapter' }));
        b.onLoad({ filter: /.*/, namespace: 'adapter' }, args => ({ contents: args.path === 'hooks'
            ? 'export const useState=(...args)=>globalThis.phase1A.useState(...args);'
            : 'export const useForm=(initial)=>globalThis.phase1A.useForm(initial); export const router={patch:(...args)=>globalThis.phase1A.calls.push(args),delete:(...args)=>globalThis.phase1A.calls.push(args)};export const Head=()=>null;export const Link=()=>null;' }));
        b.onResolve({ filter: /^@\// }, args => {
            const base = path.join(app, 'resources/js', args.path.slice(2));
            return { path: fs.existsSync(base) ? base : `${base}.jsx` };
        });
    } }],
});
const compiled = { exports: {} };
new Function('require', 'module', 'exports', bundle.outputFiles[0].text)(
    (await import('node:module')).createRequire(import.meta.url), compiled, compiled.exports,
);
const { Login, Manage } = compiled.exports;
function harness(Component, props = {}) {
    const states = []; let cursor; let data;
    const h = {
        calls: [],
        useState(initial) {
            const index = cursor++;
            if (!(index in states)) states[index] = initial;
            return [states[index], value => { states[index] = typeof value === 'function' ? value(states[index]) : value; }];
        },
        useForm(initial) {
            data ??= { ...initial };
            return { data, errors: {}, processing: false,
                setData: (key, value) => { if (typeof key === 'object') data = { ...key }; else data[key] = value; },
                clearErrors() {}, put: (...args) => h.calls.push(args),
                post: (...args) => h.calls.push(args), reset: () => { data = { ...initial }; } };
        },
        render() { cursor = 0; globalThis.phase1A = h; return Component(props); },
    };
    return h;
}
function nodes(tree, predicate) {
    if (!tree || typeof tree !== 'object') return [];
    if (Array.isArray(tree)) return tree.flatMap(child => nodes(child, predicate));
    return [...(predicate(tree) ? [tree] : []), ...nodes(tree.props?.children, predicate)];
}
const input = (tree, id) => nodes(tree, n => n.props?.id === id)[0];
globalThis.route = (name, id) => `/${name}${id ? `/${id}` : ''}`;

test('password toggle reveals and hides the same value without submitting or changing login data', () => {
    const h = harness(Login, { canResetPassword: true });
    let tree = h.render();
    input(tree, 'password').props.onChange({ target: { value: 'Synthetic#Only1' } });
    tree = h.render();
    assert.equal(input(tree, 'password').props.type, 'password');
    let button = nodes(tree, n => n.props?.['aria-controls'] === 'password')[0];
    assert.equal(button.type, 'button');
    assert.equal(button.props.type, 'button');
    assert.equal(button.props['aria-label'], 'Show password');
    assert.match(button.props.className, /focus-visible:outline/);
    button.props.onClick(); tree = h.render();
    assert.equal(input(tree, 'password').props.type, 'text');
    assert.equal(input(tree, 'password').props.value, 'Synthetic#Only1');
    assert.equal(input(tree, 'password').props.autoComplete, 'current-password');
    button = nodes(tree, n => n.props?.['aria-controls'] === 'password')[0];
    assert.equal(button.props['aria-label'], 'Hide password');
    assert.equal(h.calls.length, 0);
    button.props.onClick(); tree = h.render();
    assert.equal(input(tree, 'password').props.type, 'password');
    assert.equal(input(tree, 'password').props.value, 'Synthetic#Only1');
    nodes(tree, n => n.type === 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.equal(h.calls[0][0], '/login');
    assert.equal(nodes(tree, n => n.props?.children === 'Forgot Password?').length, 1);
});

test('year choices roll forward, deduplicate and preserve exact historical labels and creation payload', () => {
    const RealDate = Date;
    try {
        for (const year of [2026, 2035]) {
            globalThis.Date = class extends RealDate { constructor(...args) { super(...(args.length ? args : [`${year}-11-19T12:00:00`])); } };
            const periods = [{ id: 1, academic_year: '2010 - 2011' }, { id: 2, academic_year: `${year}-${year + 1}` }];
            const before = JSON.stringify(periods);
            const h = harness(Manage, { show: true, periods, onClose() {} });
            let tree = h.render();
            const select = input(tree, 'academic_year');
            assert.equal(select.type, 'select');
            assert.equal(nodes(tree, n => n.type === 'label' && n.props.htmlFor === 'academic_year').length, 1);
            const values = nodes(select, n => n.type === 'option').map(n => n.props.value);
            assert.equal(values.length, new Set(values).size);
            for (let start = year - 2; start <= year + 5; start++) assert.ok(values.includes(`${start}-${start + 1}`));
            assert.ok(values.includes('2010 - 2011'));
            select.props.onChange({ target: { value: `${year}-${year + 1}` } });
            tree = h.render();
            assert.equal(input(tree, 'academic_year').props.value, `${year}-${year + 1}`);
            const semesters = nodes(tree, n => n.type === 'select')[1];
            assert.deepEqual(nodes(semesters, n => n.type === 'option').map(n => n.props.value), ['', '1st Semester', '2nd Semester', 'Summer']);
            nodes(tree, n => n.type === 'form')[0].props.onSubmit({ preventDefault() {} });
            assert.equal(h.calls[0][0], '/review-periods.store');
            assert.equal(JSON.stringify(periods), before);
        }
    } finally { globalThis.Date = RealDate; }
});


test('editing preloads dates, requires confirmation and preserves the separate submission controls', () => {
    const period = { id: 7, academic_year: '2010 - 2011', semester: 'Summer', start_date: '2011-04-01T00:00:00.000000Z', end_date: '2011-06-01T00:00:00.000000Z', is_active: false, is_submission_open: false, evaluations_exists: true };
    const h = harness(Manage, { show: true, periods: [period], onClose() {} });
    let tree = h.render();
    nodes(tree, n => n.props?.['aria-label'] === 'Edit 2010 - 2011 Summer')[0].props.onClick();
    tree = h.render();
    assert.equal(input(tree, 'academic_year').props.value, '2010 - 2011');
    assert.equal(input(tree, 'academic_year').props.disabled, true);
    assert.equal(nodes(tree, n => n.type === 'select')[1].props.disabled, true);
    const end = nodes(tree, n => n.props?.['aria-label'] === 'End Date')[0];
    assert.equal(end.props.value, '2011-06-01');
    end.props.onChange({ target: { value: '2011-07-01' } });
    tree = h.render();
    nodes(tree, n => n.type === 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.equal(h.calls.length, 0);
    tree = h.render();
    let confirmation = nodes(tree, n => n.props?.title === 'Save period changes?')[0];
    assert.equal(confirmation.props.show, true);
    assert.match(confirmation.props.description, /2011-07-01/);
    assert.match(confirmation.props.description, /Closed evaluations stay closed/);
    confirmation.props.onCancel(); tree = h.render();
    assert.equal(h.calls.length, 0);
    nodes(tree, n => n.type === 'form')[0].props.onSubmit({ preventDefault() {} });
    tree = h.render();
    confirmation = nodes(tree, n => n.props?.title === 'Save period changes?')[0];
    confirmation.props.onConfirm();
    assert.equal(h.calls[0][0], '/review-periods.update/7');
    h.calls[0][1].onSuccess(); tree = h.render();
    assert.equal(input(tree, 'academic_year').props.value, '');
    assert.equal(input(tree, 'academic_year').props.disabled, false);
    nodes(tree, n => n.props?.['aria-label'] === 'Edit 2010 - 2011 Summer')[0].props.onClick();
    tree = h.render();
    nodes(tree, n => n.props?.children === 'Cancel editing')[0].props.onClick(); tree = h.render();
    assert.equal(input(tree, 'academic_year').props.value, '');
});

test('unused period identity fields remain editable', () => {
    const period = { id: 8, academic_year: '2026-2027', semester: 'Summer', evaluations_exists: false };
    const h = harness(Manage, { show: true, periods: [period], onClose() {} });
    let tree = h.render();
    nodes(tree, n => n.props?.['aria-label'] === 'Edit 2026-2027 Summer')[0].props.onClick();
    tree = h.render();
    assert.equal(input(tree, 'academic_year').props.disabled, false);
    assert.equal(nodes(tree, n => n.type === 'select')[1].props.disabled, false);
});
