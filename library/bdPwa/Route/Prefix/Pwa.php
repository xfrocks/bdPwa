<?php

class bdPwa_Route_Prefix_Pwa implements XenForo_Route_Interface
{
    public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
        return $router->getRouteMatch('bdPwa_ControllerPublic_Pwa', $routePath);
    }
}