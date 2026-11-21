"""Optional developer check for the owned localhost demo; Python standard library only.
Run after setup/start from a clean checkout. Never accepts a remote URL or credentials.
"""
import http.cookiejar
import html
import json
import pathlib
import re
import urllib.error
import urllib.parse
import urllib.request
import time

ROOT = pathlib.Path(__file__).resolve().parents[1]
META = json.loads((ROOT / 'storage/client-local/local-only.json').read_text())
assert META['purpose'] == 'ggc-client-local-disposable-v1'
assert pathlib.Path(META['source_root']).resolve() == ROOT
BASE = 'http://127.0.0.1:8000'


def request(opener, path, method='GET', data=None, json_body=False):
    assert path.startswith('/') and not path.startswith('//')
    headers = {'Accept': 'application/json' if json_body else 'text/html'}
    body = None
    if data is not None:
        token = csrf(opener)
        headers['X-XSRF-TOKEN'] = token
        if json_body:
            headers['Content-Type'] = 'application/json'
            body = json.dumps(data).encode()
        else:
            body = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(BASE + path, data=body, method=method, headers=headers)
    try:
        with opener.open(req, timeout=20) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()


def csrf(opener):
    status, body = request(opener, '/login')
    if status != 200:
        status, body = request(opener, '/dashboard')
    cookie = next((c.value for c in opener.demo_cookies if c.name == 'XSRF-TOKEN'), None)
    assert cookie, f'CSRF cookie missing, HTTP {status}'
    return urllib.parse.unquote(cookie)


def login(suffix):
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    opener.demo_cookies = jar
    status, body = request(opener, '/login', 'POST', {'id_number': f"DEMO-{META['installation']}-{suffix}", 'password': META['password']})
    assert status == 200 and 'data-page=' in body, f'{suffix} login failed'
    page = page_data(body)
    expected = {'ADMIN':'super_admin', 'DEPARTMENT':'department_admin', 'OFFICE':'office_admin', 'LIBRARY':'office_admin', 'STUDENT6':'student'}[suffix]
    assert page['props']['auth']['user']['role'] == expected, f'{suffix} did not authenticate as intended'
    print(f'PASS real login: {expected} ({suffix})')
    return opener


def page_data(body):
    match = re.search(r'data-page="([^"]+)"', body)
    assert match, 'Missing Inertia page'
    return json.loads(html.unescape(match.group(1)))


def api(opener, path, expected=200):
    status, body = request(opener, path, json_body=True)
    assert status == expected, f'Expected {expected}, got {status}: {path}'
    return json.loads(body) if expected == 200 else None


super_admin, department, office, library, student = [login(x) for x in ['ADMIN','DEPARTMENT','OFFICE','LIBRARY','STUDENT6']]
for actor in [super_admin, office]:
    row = next(r for r in api(actor, '/admin/reports/staff?report=summary&period_id=2')['summaryRows'] if r['name'] == 'Demonstration Registrar Staff')
    assert row['released'] and row['responses'] == 5 and row['rating'] == 3
    assert len(row['sections']) == 2
    current = next(r for r in api(actor, '/admin/reports/staff?report=summary&period_id=1')['summaryRows'] if r['name'] == 'Demonstration Registrar Staff')
    assert current['rating'] is None and current['responses'] is None and current['sections'] == []
api(library, '/admin/reports/staff?office_id=1&report=summary', 403)
api(department, '/admin/reports/staff?report=summary', 403)
api(student, '/admin/notifications', 403)
api(office, '/admin/reports/faculty?report=summary', 403)
api(department, '/admin/reports/office?report=summary', 403)
api(department, '/admin/reports/faculty?report=summary&period_id=2')
assert all('Registrar' not in row['officeName'] for row in api(library, '/admin/reports/staff?report=summary')['summaryRows'])
print('PASS released/withheld fixtures, section scores and role/office boundaries')

status, body = request(student, '/evaluation')
props = page_data(body)['props']
before_required = props['progress']
member = next(x for x in props['officeStaff']['data'] if x['name'] == 'Demonstration Registrar Staff')
ratings = {str(q['id']):4 for category in member['categories'] for q in category['questions']}
status, body = request(student, f"/evaluation/staff/{member['id']}", 'POST', {'ratings':ratings, 'comments':'Synthetic smoke feedback'}, True)
assert status in [200, 302]
status, body = request(student, f"/evaluation/staff/{member['id']}", 'POST', {'ratings':ratings}, True)
assert status == 422
status, body = request(student, '/evaluation')
assert page_data(body)['props']['progress'] == before_required
print('PASS actual Staff submission/duplicate validation and independent required progress')
for kind, collection in [('faculty','faculties'),('office','offices')]:
    target = props[collection]['data'][0] if kind == 'faculty' else next(x for x in props[collection]['data'] if x['name'] == 'Demonstration Registrar')
    answers = {str(q['id']):3 for category in target['categories'] for q in category['questions']}
    status, _ = request(student, f"/evaluation/{kind}/{target['id']}", 'POST', {'ratings':answers}, True)
    assert status in [200,302]
status, body = request(student, '/evaluation')
assert page_data(body)['props']['progress']['completed'] == before_required['completed'] + 2
print('PASS actual Faculty/Office submission flow and required completion')

path = '/admin/review-periods/1/toggle-submission'
request(super_admin, path, 'PATCH', {}, True)
for _ in range(30):
    notices = api(office, '/admin/notifications')
    if any('1st Semester' in item['period'] for item in notices['items']):
        break
    time.sleep(.3)
else:
    raise AssertionError('Closure notification was not processed by the automatic local worker')
row = next(r for r in api(office, '/admin/reports/staff?report=summary&period_id=1')['summaryRows'] if r['name'] == 'Demonstration Registrar Staff')
assert row['responses'] == 5 and row['rating'] == 3.2
assert api(library, '/admin/notifications')['items'] == []
assert {item['kind'] for item in api(department, '/admin/notifications')['items']} == {'faculty'}
notice = notices['items'][0]
status, _ = request(office, '/admin/notifications/'+notice['id'], 'PATCH', {'read':True}, True)
assert status == 200
status, _ = request(library, '/admin/notifications/'+notice['id'], 'PATCH', {'read':True}, True)
assert status == 404
status, _ = request(office, '/admin/notifications/'+notice['id'], 'PATCH', {'read':False}, True)
assert status == 200
for item in notices['items']:
    assert set(item) == {'id','kind','read','title','subject','period','message','url'}
print('PASS closure, queued delivery, read/unread and forged notification ID protection')

request(super_admin, path, 'PATCH', {}, True)
row = next(r for r in api(office, '/admin/reports/staff?report=summary&period_id=1')['summaryRows'] if r['name'] == 'Demonstration Registrar Staff')
assert row['rating'] is None and row['responses'] is None
assert not any('1st Semester' in item['period'] for item in api(office, '/admin/notifications')['items'])
# PHP endpoints must still use the guarded runtime instead of a developer .env entry point.
status, _ = request(student, '/index.php')
assert status in [200,404]
for private_path in ['/.env','/storage/client-local/local-only.json','/scripts/client-local.php','/vendor/composer/installed.json']:
    status, _ = request(student, private_path)
    assert status == 404, f'Private path was reachable: {private_path}'
print('PASS reopening suppresses Staff results; private configuration/runtime paths are inaccessible')
print('PASS: clean local setup uses actual authentication, permissions, submissions and database queue processing.')
