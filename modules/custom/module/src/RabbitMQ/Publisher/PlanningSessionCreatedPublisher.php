<?php

namespace Drupal\hello_world\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hello_world\RabbitMQ\Message\RegistrationMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Drupal\user\Entity\User;

class PlanningSessionCreatedPublisher extends FormBase
{
    public function getFormId(): string
    {
        // TODO(nasr): add this with jelle's code
    }

    public function buildForm(
        array $form,
        FormStateInterface $form_state,
    ): array {
        // TODO(nasr):
    }




    $client = RabbitMQClient::fromEnv();

    try {

    // TODO(nasr): message needs to come from jelle's form stuff
    $client->publish($message);


    }
    catch(\RuntimeException $e) {

        // TODO(nasr): replace with controlroom logger
        \Drupal::logger('hello_world')->error(
          'RabbitMQ publish mislukt: @err', ['@err' => $e->getMessage()]
    }
    finally {

    $client->disconnect();
    }


    $this->messenger()->addStatus(
        $this->t('Planning session Created!');j

    );

    // What is this?
    $form_state->setRedirectUrl(Url::fromRoute(''));

    private function setField(object $entity, string $field, mixed $value): void  {

        if ($value !== NULL && $entity->hasField($field)) {
           
            $entity->set($field, $value);
            
        }

    }




}
