<?php

namespace Drupal\custom_roles\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Session\AccountInterface;

class LoginRedirectionSubscriber implements EventSubscriberInterface {

  protected $currentUser;

  /**
   * We injecteren de huidige gebruiker om de rollen te kunnen checken.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * We vertellen Drupal dat we willen reageren op het 'Response' event.
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onRespond', 10];
    return $events;
  }

  /**
   * De logica die wordt uitgevoerd bij elke response.
   */
  public function onRespond(ResponseEvent $event) {
    $request = $event->getRequest();

    // BELANGRIJK: Check nu ook of de gebruiker "isAuthenticated" (ingelogd) is
    if ($this->currentUser->isAuthenticated() && $request->attributes->get('_route') === 'user.login') {
      
      $roles = $this->currentUser->getRoles();

      if (in_array('administrator', $roles)) {
        $url = '/hello/admin';
      }
      elseif (in_array('speaker', $roles)) {
        $url = '/bespreker';
      }
      else {
        $url = '/';
      }

      $event->setResponse(new RedirectResponse($url));
    }
  }
}