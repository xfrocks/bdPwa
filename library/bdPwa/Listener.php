<?php

class bdPwa_Listener
{
    const SERVICE_WORKER_VERSION = 2016112615;

    /**
     * @param string $contents
     * @param array $params
     * @param XenForo_Template_Abstract $template
     * @return string
     */
    public static function renderPageContainerJsHead($contents, $params, $template)
    {
        $visitorStyle = $template->getParam('visitorStyle');
        $visitorLanguage = $template->getParam('visitorLanguage');

        $urlParams = array(
            'l' => $visitorLanguage['language_id'],
            's' => $visitorStyle['style_id'],
            'v' => self::SERVICE_WORKER_VERSION,
        );
        $manifestJsonUrl = XenForo_Link::buildPublicLink('full:pwa/manifest.json', '', $urlParams);

        // we need to build service-worker.js url ourselves because it must resides
        // on XenForo root and cannot be deep under pwa etc. (which may be the case if
        // seo friendly option is turned on)
        $serviceWorkerJsUrl = XenForo_Link::buildPublicLink('full:index');
        $serviceWorkerJsUrl = preg_replace('#index\.php$#', '', $serviceWorkerJsUrl);
        $serviceWorkerJsUrlParts = array($serviceWorkerJsUrl, 'index.php?pwa/service-worker.js');
        foreach ($urlParams as $urlParamKey => $urlParamValue) {
            $serviceWorkerJsUrlParts[] = sprintf('&%s=%s', $urlParamKey, urlencode($urlParamValue));
        }
        $serviceWorkerJsUrl = implode($serviceWorkerJsUrlParts);

        $scripts = array(
            '<link rel="manifest" href="',
            XenForo_Template_Helper_Core::jsEscape($manifestJsonUrl, 'double'),
            '" />',
            '<script>',
            'if("serviceWorker" in navigator){',
            'navigator.serviceWorker.register("',
            XenForo_Template_Helper_Core::jsEscape($serviceWorkerJsUrl, 'double'),
            '")',
        );

        if (XenForo_Application::debugMode()) {
            $scripts = array_merge($scripts, array(
                '.then(function(r){console.info("SW ok",r)})',
                '.catch(function(e){console.error("SW failed",e)})',
            ));
        }

        $scripts[] = '}</script>';
        return implode('', $scripts);
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes += bdPwa_FileSums::getHashes();
    }
}