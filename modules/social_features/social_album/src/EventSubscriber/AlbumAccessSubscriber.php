<?php

namespace Drupal\social_album\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;

/**
 * Restrict access to album feature when it's disabled.
 */
class AlbumAccessSubscriber implements EventSubscriberInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current route.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRoute;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs AlbumAccess Subscriber.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The current route.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    RequestStack $request_stack,
    CurrentRouteMatch $route_match,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->requestStack = $request_stack;
    $this->currentRoute = $route_match;
    $this->configFactory = $configFactory;
  }

  /**
   * Custom album access check.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   A RequestEvent instance.
   */
  public function customAlbumAccess(RequestEvent $event): void {
    $request = $this->requestStack->getCurrentRequest();

    // Do not execute on drush.
    if (PHP_SAPI === 'cli') {
      return;
    }

    // Do nothing on a sub request.
    if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
      return;
    }

    if (!$request instanceof Request) {
      return;
    }

    // Check if path is album listed.
    if (!$this->isAlbumPath($request)) {
      return;
    }

    // Check if album feature is active.
    if ($this->configFactory->get('social_album.settings')->get('status')) {
      return;
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Checks if the path is album listed.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return bool
   *   TRUE when the path is album listed.
   */
  public function isAlbumPath(Request $request): bool {
    $is_album = FALSE;
    $request_url = $request->server->get('REQUEST_URI');
    $route_match = $this->currentRoute->getRouteName();

    switch ($route_match) {
      case 'node.add':
        if (strpos($request_url, '/node/add/album') !== FALSE) {
          $is_album = TRUE;
        }
        break;

      case 'entity.node.canonical':
      case 'entity.node.edit_form':
        $node = $request->get('node');
        if ($node instanceof NodeInterface && $node->bundle() === 'album') {
          $is_album = TRUE;
        }
        break;
    }

    return $is_album;
  }

  /**
   * Listen to kernel.request events and call custom access check.
   *
   * @return array
   *   Event names to listen to (key) and methods to call (value).
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['customAlbumAccess'];
    return $events;
  }

}
