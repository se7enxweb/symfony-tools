<?php

/**
 * HTTP Cache Control Header Listener
 * 
 * Overrides aggressive cache directives from FOSHttpCacheBundle
 * and applies proper public caching headers for cacheable content.
 * 
 * This listener runs AFTER all other FOSHttpCache subscribers and ensures
 * that public pages get the correct Cache-Control headers for proxy/shared caching.
 */

namespace Sevenx\SymfonyToolsBundle\EventListener;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CacheControlHeaderListener implements EventSubscriberInterface
{
    public function __construct()
    {
        error_log("CacheControlHeaderListener::__construct - listener service instantiated");
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        // Priority -1025: Run AFTER ALL other listeners including StreamedResponseListener (-1024)
        // This ensures we can override any headers set by other listeners
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1025],
        ];
    }

    /**
     * Apply cache control headers based on response type and route
     * 
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        
        // DEBUG: Write to accessible file location
        $isMaster = $event->isMasterRequest() ? 'MASTER' : 'SUBREQUEST';
        @file_put_contents('/var/www/vhosts/platform.cjw.alpha.se7enx.com/doc/var/logs/cache_listener.log', "FIRED at " . date('Y-m-d H:i:s') . " for " . $request->getPathInfo() . " (status: " . $response->getStatusCode() . ") [$isMaster]\n", FILE_APPEND);
        
        // Skip subrequests (only process main request)
        if (!$event->isMasterRequest()) {
            return;
        }

        $path = $request->getPathInfo();
        
        // SKIP: Keep private ONLY for login/logout/auth routes
        if (strpos($path, '/login') === 0 || strpos($path, '/logout') === 0) {
            return;
        }

        // SKIP: Keep private for responses with Set-Cookie (session-dependent)
        if ($response->headers->has('Set-Cookie')) {
            return;
        }

        // SKIP: Keep private if it has X-User-Context-Hash (user-specific context)
        if ($response->headers->has('X-User-Context-Hash')) {
            return;
        }

        // For all OTHER responses: apply aggressive public caching
        // This overrides FOSHttpCache's conservative defaults
        // Check condition first
        $statusCode = $response->getStatusCode();
        $shouldCache = $response->isSuccessful() || $response->isNotFound() || $response->isRedirection();
        
        if ($shouldCache) {
            
            $oldCC = $response->headers->get('Cache-Control', 'NONE');
            
            // Use the raw header approach - directly set via PHP headers
            // This avoids Symfony's cache-control directive object merging
            if (function_exists('header_remove')) {
                header_remove('Cache-Control');
            }
            $response->headers->remove('Cache-Control');
            
            // Set new raw header value
            $newCacheControl = 'public, max-age=3600, s-maxage=3600, must-revalidate';
            $response->headers->set('Cache-Control', $newCacheControl);
            
            $newCC = $response->headers->get('Cache-Control', 'NONE');
            @file_put_contents('/var/www/vhosts/platform.cjw.alpha.se7enx.com/doc/var/logs/cache_listener.log', "  Old CC: $oldCC\n  Setting: $newCacheControl\n  New CC: $newCC\n", FILE_APPEND);
            
            // Remove old browser cache headers
            $response->headers->remove('Pragma');
            $response->headers->remove('Expires');
        }
    }
}
