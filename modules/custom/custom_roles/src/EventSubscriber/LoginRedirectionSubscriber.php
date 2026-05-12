<?php

namespace Drupal\custom_roles\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

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
      $url = '/user'; 

      if (in_array('administrator', $roles)) {
        $url = Url::fromUri('internal:/hello/admin')->setAbsolute()->toString();
      } 
      elseif (in_array('speaker', $roles)) {
        $url = Url::fromUri('internal:/bespreker')->setAbsolute()->toString();
      }
      else {
      $url = Url::fromUri('internal:/')->setAbsolute()->toString();
    }

      $event->setResponse(new RedirectResponse($url));
    }
  }
}