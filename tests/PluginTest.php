<?php

// Plain PHP tests for the tag builder and settings helpers. Run: php tests/PluginTest.php

// Loading the plugin file without $Wcms only defines the helper class.
require __DIR__ . '/../wondercms-privatus-analytics.php';

$failures = 0;
function check($name, $expected, $actual)
{
	global $failures;
	if ($expected === $actual) {
		echo "ok   $name\n";
		return;
	}
	$failures++;
	echo "FAIL $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

// A small stand-in for the WonderCMS object.
class FakeWcms
{
	public $db;

	public function __construct($config)
	{
		$this->db = (object) ['config' => (object) $config];
	}

	public function getDb()
	{
		return $this->db;
	}

	public function getToken()
	{
		return 'tok"en';
	}

	public function url($location = '')
	{
		return 'https://example.com/' . $location;
	}
}

$all = ['engage', 'auto', 'vitals', 'clicks', 'ask'];
$P = 'PrivatusAnalyticsWcms';

check(
	'full tag',
	'<script defer src="https://privatusstats.com/js/pa.js" data-site="pa_YOURSITEID" data-modules="engage,auto,vitals,clicks,ask"></script>',
	$P::tag('pa_YOURSITEID', $all)
);
check('empty site id prints nothing', '', $P::tag('', $all));
check('site id without pa_ prints nothing', '', $P::tag('YOURSITEID', $all));
check('quote in site id prints nothing', '', $P::tag('pa_x" onload="alert(1)', $all));
check('no modules gives an empty list', true, strpos($P::tag('pa_X', []), 'data-modules=""') !== false);
check('unknown modules are dropped', true, strpos($P::tag('pa_X', ['ask', '<x>', 'auto']), 'data-modules="auto,ask"') !== false);

$saved = $P::fromPost(['privatusAnalyticsSiteId' => ' pa_Abc1 ', 'privatusAnalyticsModules' => ['vitals', 'nope', 'engage']]);
check('post keeps a valid site id', 'pa_Abc1', $saved['siteId']);
check('post keeps known modules in order', ['engage', 'vitals'], $saved['modules']);
check('post with skip unticked turns it off', false, $saved['skipLoggedIn']);
check('post drops a bad site id', '', $P::fromPost(['privatusAnalyticsSiteId' => 'pa_<b>'])['siteId']);
check('post with no modules saves none', [], $P::fromPost(['privatusAnalyticsSiteId' => 'pa_A'])['modules']);
check('post ignores a modules string', [], $P::fromPost(['privatusAnalyticsModules' => 'engage'])['modules']);

$fresh = $P::settings(new FakeWcms(['siteTitle' => 'x']));
check('defaults: no site id', '', $fresh['siteId']);
check('defaults: no extra modules', [], $fresh['modules']);
check('defaults: skip logged in', true, $fresh['skipLoggedIn']);

$stored = $P::settings(new FakeWcms(['privatusAnalytics' => (object) ['siteId' => 'pa_S', 'modules' => ['clicks'], 'skipLoggedIn' => false]]));
check('stored settings are read', ['siteId' => 'pa_S', 'modules' => ['clicks'], 'skipLoggedIn' => false], $stored);

$asObject = $P::settings(new FakeWcms(['privatusAnalytics' => (object) ['siteId' => 'pa_S', 'modules' => (object) ['0' => 'engage', '1' => 'ask']]]));
check('modules saved as a JSON object are read', ['engage', 'ask'], $asObject['modules']);
$none = $P::settings(new FakeWcms(['privatusAnalytics' => (object) ['siteId' => 'pa_S', 'modules' => (object) []]]));
check('an empty saved list stays empty', [], $none['modules']);

$form = $P::form(new FakeWcms(['privatusAnalytics' => (object) ['siteId' => 'pa_S', 'modules' => ['clicks'], 'skipLoggedIn' => true]]));
check('form escapes the token', true, strpos($form, 'value="tok&quot;en"') !== false);
check('form ticks saved modules only', true, strpos($form, 'value="clicks" checked') !== false && strpos($form, 'value="engage" checked') === false);

$settingsHtml = '<div class="tab-pane" id="menu"><p>a</p><div class="change">b</div></div><div role="tabpanel" class="tab-pane" id="files">c</div>';
check(
	'form goes at the end of the Menu tab',
	'<div class="tab-pane" id="menu"><p>a</p><div class="change">b</div>FORM</div><div role="tabpanel" class="tab-pane" id="files">c</div>',
	$P::addToSettings($settingsHtml, 'FORM')
);
check('form is appended when there is no Menu tab', 'xFORM', $P::addToSettings('x', 'FORM'));

$meta = json_decode(file_get_contents(__DIR__ . '/../wcms-modules.json'), true);
check('wcms-modules.json is valid', 1, $meta['version'] ?? null);
check('wcms-modules.json version matches the version file', trim(file_get_contents(__DIR__ . '/../version')), $meta['plugins']['wondercms-privatus-analytics']['version'] ?? null);

if ($failures > 0) {
	echo "\n$failures failed\n";
	exit(1);
}
echo "\nall passed\n";
