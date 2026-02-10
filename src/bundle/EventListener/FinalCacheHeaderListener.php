<?php

/**
 * Final Cache Control Header Listener
 * 
 * This listener applies cache header fixes at the lowest possible priority
 * within Symfony's event system. It uses PHP_INT_MIN to ensure it runs
 * after all other kernel.response listeners.
 * 
 * The challenge: Symfony listeners all run before Response::send() is called,
 * so by necessity we run here rather than during actual header transmission.
 * However, this is the absolute latest point where we can modify headers
 * within the Symfony event system.
 */

namespace Sevenx\SymfonyToolsBundle\EventListener;

use AppBundle\Service\CacheHeaderFixer;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FinalCacheHeaderListener implements EventSubscriberInterface
{
    /**
     * Event subscriptions using PHP_INT_MIN for absolute lowest priority
     */
    public static function getSubscribedEvents()
    {
        // PHP_INT_MIN ensures this runs absolutely last among all listeners
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', PHP_INT_MIN],
        ];
    }

    /**
     * Apply final cache header fixes
     * 
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $response = $event->getResponse();
        
        // Skip subrequests - only process main request responses
        if (!$event->isMasterRequest()) {
            return;
        }
        
        // Use the shared cache header fixer service
        CacheHeaderFixer::fixResponse($response);
    }
}
