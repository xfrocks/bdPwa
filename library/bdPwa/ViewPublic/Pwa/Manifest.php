<?php

class bdPwa_ViewPublic_Pwa_Manifest extends XenForo_ViewPublic_Base
{
    public function renderJson()
    {
        $this->_response->setHeader('Cache-Control', 'max-age=86400', true);
        $this->_response->setHeader('Content-Type', 'application/manifest+json', true);

        return $this->_params['manifest'];
    }
}