<?php

/**
 * Privatus Analytics for WonderCMS.
 *
 * Adds the Privatus Analytics tracking script to the <head> of every
 * page. It only prints a script tag and never sends data from the server.
 * Settings live in the Menu tab of the WonderCMS settings panel.
 */

global $Wcms;

if (!class_exists('PrivatusAnalyticsWcms')) {
	class PrivatusAnalyticsWcms
	{
		const SCRIPT_URL = 'https://privatusstats.com/js/pa.js';
		const SITE_ID_PATTERN = '/^pa_[A-Za-z0-9]+$/';
		const CONFIG_KEY = 'privatusAnalytics';

		/** Tracker modules: name => label. */
		const MODULES = [
			'engage' => 'Time on page and how far people scroll',
			'auto' => 'Clicks on outside links, file downloads, forms people send and missing pages (404)',
			'vitals' => 'Page speed (Web Vitals)',
			'clicks' => 'Click maps',
			'ask' => 'Short surveys on your site',
		];

		/**
		 * The saved settings, with defaults for anything missing.
		 *
		 * @return array{siteId: string, modules: string[], skipLoggedIn: bool}
		 */
		public static function settings($Wcms): array
		{
			$config = $Wcms->getDb()->config ?? null;
			$saved = is_object($config) && isset($config->{self::CONFIG_KEY}) ? (array) $config->{self::CONFIG_KEY} : [];

			return [
				'siteId' => isset($saved['siteId']) && is_string($saved['siteId']) ? $saved['siteId'] : '',
				// WonderCMS saves lists as JSON objects, so a list can come back as an object.
				'modules' => isset($saved['modules']) && (is_array($saved['modules']) || is_object($saved['modules']))
					? array_values(array_filter((array) $saved['modules'], 'is_string'))
					: [],
				'skipLoggedIn' => isset($saved['skipLoggedIn']) ? (bool) $saved['skipLoggedIn'] : true,
			];
		}

		/**
		 * Turns posted form fields into settings. A site ID that is not valid is dropped.
		 *
		 * @param array<string, mixed> $post
		 * @return array{siteId: string, modules: string[], skipLoggedIn: bool}
		 */
		public static function fromPost(array $post): array
		{
			$siteId = isset($post['privatusAnalyticsSiteId']) ? trim((string) $post['privatusAnalyticsSiteId']) : '';
			$posted = isset($post['privatusAnalyticsModules']) && is_array($post['privatusAnalyticsModules']) ? $post['privatusAnalyticsModules'] : [];

			return [
				'siteId' => self::isValidSiteId($siteId) ? $siteId : '',
				'modules' => array_values(array_intersect(array_keys(self::MODULES), $posted)),
				'skipLoggedIn' => !empty($post['privatusAnalyticsSkipLoggedIn']),
			];
		}

		public static function isValidSiteId($siteId): bool
		{
			return is_string($siteId) && preg_match(self::SITE_ID_PATTERN, $siteId) === 1;
		}

		/**
		 * Builds the tracker tag. Returns an empty string when the site ID is not valid.
		 *
		 * @param string[] $modules Tracker modules to load. An empty list loads none.
		 */
		public static function tag($siteId, array $modules): string
		{
			$siteId = trim((string) $siteId);
			if (!self::isValidSiteId($siteId)) {
				return '';
			}
			$modules = array_values(array_intersect(array_keys(self::MODULES), $modules));

			return '<script defer src="' . self::SCRIPT_URL . '" crossorigin="anonymous"'
				. ' data-site="' . self::escape($siteId) . '"'
				. ' data-modules="' . self::escape(implode(',', $modules)) . '"></script>';
		}

		/** No tag on the login page, and none for a logged-in admin unless the owner wants it. */
		public static function skip($Wcms, array $settings): bool
		{
			return $Wcms->currentPage === $Wcms->get('config', 'login')
				|| ($settings['skipLoggedIn'] && $Wcms->loggedIn);
		}

		/** The settings block shown in the Menu tab of the settings panel. */
		public static function form($Wcms): string
		{
			$settings = self::settings($Wcms);

			$html = '<p class="subTitle">Privatus Analytics</p>';
			$html .= '<div class="change"><form action="' . self::escape(self::currentUrl($Wcms)) . '" method="post">';
			$html .= '<p>See how many real people visit your site. The plugin sets no cookies of its own. Paste your site ID from '
				. '<a href="https://privatusanalytics.com" target="_blank" rel="noopener">privatusanalytics.com</a>. '
				. 'It starts with pa_ and you find it under Site settings, Tracking.</p>';
			$html .= '<div class="form-group" style="margin-bottom:10px"><input type="text" class="wform-control" name="privatusAnalyticsSiteId" placeholder="pa_YOURSITEID" pattern="pa_[A-Za-z0-9]+" value="' . self::escape($settings['siteId']) . '"></div>';
			foreach (self::MODULES as $module => $label) {
				$checked = in_array($module, $settings['modules'], true) ? ' checked' : '';
				$html .= '<div><label><input type="checkbox" name="privatusAnalyticsModules[]" value="' . $module . '"' . $checked . '> ' . self::escape($label) . '</label></div>';
			}
			$checked = $settings['skipLoggedIn'] ? ' checked' : '';
			$html .= '<div><label><input type="checkbox" name="privatusAnalyticsSkipLoggedIn" value="1"' . $checked . '> Don\'t count my own visits while I am logged in</label></div>';
			$html .= '<input type="hidden" name="token" value="' . self::escape($Wcms->getToken()) . '">';
			$html .= '<input type="hidden" name="privatusAnalyticsSave" value="1">';
			$html .= '<button type="submit" class="wbtn wbtn-info marginTop5">Save Privatus Analytics settings</button>';
			$html .= '</form></div>';

			return $html;
		}

		/** Adds the settings block to the end of the Menu tab. */
		public static function addToSettings(string $output, string $form): string
		{
			$marker = 'id="menu">';
			$start = strpos($output, $marker);
			if ($start === false) {
				return $output . $form;
			}
			$next = strpos($output, 'role="tabpanel"', $start + strlen($marker));
			if ($next === false) {
				return $output . $form;
			}
			// Step back to the end of the Menu tab, just before the next tab opens.
			$cut = strrpos(substr($output, 0, $next), '</div>');
			if ($cut === false) {
				return $output . $form;
			}

			return substr($output, 0, $cut) . $form . substr($output, $cut);
		}

		private static function currentUrl($Wcms): string
		{
			return method_exists($Wcms, 'getCurrentPageUrl') ? $Wcms->getCurrentPageUrl() : $Wcms->url();
		}

		private static function escape($value): string
		{
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		}
	}
}

if (isset($Wcms) && is_object($Wcms)) {
	// Save the settings form.
	if (isset($_POST['privatusAnalyticsSave']) && $Wcms->verifyFormActions()) {
		$Wcms->set('config', PrivatusAnalyticsWcms::CONFIG_KEY, (object) PrivatusAnalyticsWcms::fromPost($_POST));
		$Wcms->alert('success', 'Privatus Analytics settings saved.');
		$Wcms->redirect(method_exists($Wcms, 'getCurrentPagePath') ? $Wcms->getCurrentPagePath() : '');
	}

	// Print the tag in the head. Themes print $Wcms->css() inside <head>.
	$Wcms->addListener('css', function ($args) {
		global $Wcms;
		$settings = PrivatusAnalyticsWcms::settings($Wcms);
		if (PrivatusAnalyticsWcms::skip($Wcms, $settings)) {
			return $args;
		}
		$args[0] .= PrivatusAnalyticsWcms::tag($settings['siteId'], $settings['modules']);

		return $args;
	});

	$Wcms->addListener('settings', function ($args) {
		global $Wcms;
		$args[0] = PrivatusAnalyticsWcms::addToSettings($args[0], PrivatusAnalyticsWcms::form($Wcms));

		return $args;
	});
}
