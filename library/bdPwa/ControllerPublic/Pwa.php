<?php

class bdPwa_ControllerPublic_Pwa extends XenForo_ControllerPublic_Abstract
{
    public function actionServiceWorker()
    {
        if ($this->_routeMatch->getResponseType() !== 'js') {
            return $this->responseNoPermission();
        }

        $data = $this->_actionServiceWorker_getData();

        $scripts = array(
            'let data=',
            json_encode($data),
            ';',
            'importScripts(data._scriptUrl);',
            'self.addEventListener("install",function(e){pwa.onInstall(data,e)});',
            'self.addEventListener("activate",function(e){pwa.onActivate(data,e)});',
            'self.addEventListener("fetch",function(e){pwa.onFetch(data,e)});',
        );

        if ($this->_request->getParam('_debug')
            && XenForo_Application::debugMode()
        ) {
            // for debug output...
            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('full:index'));
        }

        header('Cache-control: private');
        header('Content-Type: text/javascript; charset=UTF-8');
        die(implode('', array_map('trim', $scripts)));
    }

    public function actionManifest()
    {
        if ($this->_routeMatch->getResponseType() !== 'json') {
            return $this->responseNoPermission();
        }

        $viewParams = array('manifest' => $this->_getManifest_getData());

        return $this->responseView('bdPwa_ViewPublic_Pwa_Manifest', '', $viewParams);
    }

    protected function _actionServiceWorker_getData()
    {
        $data = array(
            'cacheNetworkFirstMaxItems' => 30,
            'debugMode' => XenForo_Application::debugMode(),
            'fullIndex' => XenForo_Link::buildPublicLink('full:index'),
            'jsLastUpdate' => intval(XenForo_Application::getOptions()->get('jsLastUpdate')),
            'languageTextDirection' => 'LTR',
            'preCaches' => array('css' => array(), 'js' => array(), 'other' => array()),
            'styleLastModifiedDate' => 0,
            'version' => bdPwa_Listener::SERVICE_WORKER_VERSION,
        );

        $data['fullIndex'] = preg_replace('#index\.php$#', '', $data['fullIndex']);

        $language = $this->_getRequestedLanguage();
        if ($language !== null) {
            $data['languageTextDirection'] = $language['text_direction'];
        }

        $style = $this->_getRequestedStyle();
        if ($style !== null) {
            $data['styleLastModifiedDate'] = $style['last_modified_date'];
        }

        $data['_scriptUrl'] = sprintf('js/bdPwa/%s.js?%d', (XenForo_Application::debugMode()
            ? 'full/service-worker' : 'service-worker.min'), bdPwa_Listener::SERVICE_WORKER_VERSION);

        return $data;
    }

    protected function _getManifest_getData()
    {
        $xenOptions = XenForo_Application::getOptions();
        $shortName = $xenOptions->get('bdPwa_manifestShortName');
        if (empty($shortName)) {
            $shortName = $xenOptions->get('boardTitle');
        }

        $icons = array();
        $iconUrls = preg_split('#\s#', $xenOptions->get('bdPwa_manifestIcons'), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($iconUrls as $iconUrl) {
            $iconSize = bdPwa_ShippableHelper_ImageSize::calculate($iconUrl);
            if ($iconSize['width'] > 0 && $iconSize['height'] > 0) {
                $icons[] = array(
                    'src' => XenForo_Link::convertUriToAbsoluteUri($iconUrl, true),
                    'sizes' => sprintf('%dx%d', $iconSize['width'], $iconSize['height']),
                );
            }
        }

        $data = array(
            'name' => $xenOptions->get('boardTitle'),
            'description' => $xenOptions->get('boardDescription'),
            'short_name' => $shortName,
            'icons' => $icons,
            'start_url' => XenForo_Link::buildPublicLink('full:index')
                . '#utm_source=mobile&utm_medium=homescreen&utm_campaign=pwa',
            'display' => $xenOptions->get('bdPwa_manifestDisplay'),
        );

        $language = $this->_getRequestedLanguage();
        if ($language !== null) {
            $data['lang'] = $language['language_code'];
            if (preg_match('#(?<codeOnly>\w+)\-(\w+)#', $data['lang'], $matches)) {
                $data['lang'] = $matches['codeOnly'];
            }
            $data['dir'] = strtolower($language['text_direction']);
        }

        return $data;
    }

    protected function _getRequestedLanguage()
    {
        $languageId = $this->_input->filterSingle('l', XenForo_Input::UINT);
        if ($languageId > 0 && XenForo_Application::isRegistered('languages')) {
            $languages = XenForo_Application::get('languages');
            if (isset($languages[$languageId])) {
                return $languages[$languageId];
            }
        }

        return null;
    }

    protected function _getRequestedStyle()
    {
        $styleId = $this->_input->filterSingle('s', XenForo_Input::UINT);
        if ($styleId > 0 && XenForo_Application::isRegistered('styles')) {
            $styles = XenForo_Application::get('styles');
            if (isset($styles[$styleId])) {
                return $styles[$styleId];
            }
        }

        return null;
    }

    protected function _setupSession($action)
    {
        return;
    }
}