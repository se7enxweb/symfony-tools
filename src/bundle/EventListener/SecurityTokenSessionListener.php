<?php

namespace Sevenx\SymfonyToolsBundle\EventListener;

use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Listener to ensure security tokens are properly serialized to session
 * after form_login authentication succeeds
 */
class SecurityTokenSessionListener
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Called when an interactive login occurs
     */
    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        error_log("SecurityTokenSessionListener::onSecurityInteractiveLogin() - User has been authenticated!");
        
        $request = $event->getRequest();
        $token = $event->getAuthenticationToken();
        
        if ($token && $request->hasSession()) {
            $session = $request->getSession();
            // Force the security token to be stored in the session
            // The session key format depends on the firewall name
            $session->set('_security_ezpublish_front', serialize($token));
            error_log("SecurityTokenSessionListener: Stored token in session under key '_security_ezpublish_front'");
            error_log("SecurityTokenSessionListener: Token class: " . get_class($token));
            error_log("SecurityTokenSessionListener: User: " . ($token->getUser() ? $token->getUser()->getUsername() : "N/A"));
        }
    }
}
