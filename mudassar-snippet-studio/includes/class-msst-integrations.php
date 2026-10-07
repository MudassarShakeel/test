<?php
/**
 * Generates tracking markup from validated IDs.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Integration output.
 */
class MSST_Integrations {

	/**
	 * Markup for one slot.
	 *
	 * @param string $slot   header|body|footer.
	 * @param array  $options Global settings.
	 * @return string
	 */
	public static function render( $slot, array $options ) {
		$out = '';
		$ga4 = MSST_Settings::clean_integration_id( 'ga4', $options['ga4'] );
		$gtm = MSST_Settings::clean_integration_id( 'gtm', $options['gtm'] );
		$fb  = MSST_Settings::clean_integration_id( 'meta', $options['meta'] );
		$tt  = MSST_Settings::clean_integration_id( 'tiktok', $options['tiktok'] );

		if ( 'header' === $slot ) {
			if ( $ga4 ) {
				$out .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ga4 ) . '"></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Fixed Google origin, ID validated by strict pattern; printed in <head> by design.
				$out .= '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . esc_js( $ga4 ) . '");</script>' . "\n";
			}
			if ( $gtm ) {
				$out .= '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","' . esc_js( $gtm ) . '");</script>' . "\n";
			}
			if ( $fb ) {
				$out .= '<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");fbq("init","' . esc_js( $fb ) . '");fbq("track","PageView");</script>' . "\n";
			}
			if ( $tt ) {
				$out .= '<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e){var n="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._t=ttq._t||{};ttq._t[e]=+new Date;var o=d.createElement("script");o.type="text/javascript";o.async=!0;o.src=n+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load("' . esc_js( $tt ) . '");ttq.page()}(window,document,"ttq");</script>' . "\n";
			}
		}
		if ( 'body' === $slot && $gtm ) {
			$out .= '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $gtm ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
		}
		return $out;
	}
}
