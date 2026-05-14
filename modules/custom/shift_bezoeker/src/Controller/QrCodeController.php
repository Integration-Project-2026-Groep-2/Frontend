<?php

namespace Drupal\shift_bezoeker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends ControllerBase {

  /**
   * Display the QR code page.
   * @return array
   * Render array for the QR code page.
   */
  public function displayQrCode() {
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    $uuid = $user ? $this->resolveUserIdentifier($user) : '';

    if (empty($uuid)) {
      $this->messenger()->addError($this->t('Unable to generate QR code. User ID not found.'));
      return [
        '#theme' => 'qr_code',
        '#qr_image' => '',
        '#user_name' => $user ? $user->getDisplayName() : '',
        '#user_uuid' => '',
        '#error' => TRUE,
      ];
    }

    $qrImageDataUri = $this->buildQrCodeDataUri($uuid);

    return [
      '#theme' => 'qr_code',
      '#qr_image' => $qrImageDataUri,
      '#user_name' => $user->getDisplayName(),
      '#user_uuid' => $uuid,
      '#error' => FALSE,
    ];
  }

  /**
   * Download the QR code as PNG.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Binary file response with PNG data.
   */
  public function downloadQrCode() {
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    $uuid = $user ? $this->resolveUserIdentifier($user) : '';

    if (empty($uuid)) {
      return new Response(
        $this->t('Unable to generate QR code. User ID not found.'),
        Response::HTTP_NOT_FOUND
      );
    }

    $result = (new Builder())->build(
      writer: new PngWriter(),
      data: $uuid,
      size: 300,
      margin: 10,
    );

    return new Response($result->getString(), Response::HTTP_OK, [
      'Content-Type' => 'image/png',
      'Content-Disposition' => 'attachment; filename="qrcode.png"',
    ]);
  }

  /**
   * Build a QR code data URI for display.
   */
  private function buildQrCodeDataUri(string $uuid): string {
    $result = (new Builder())->build(
      writer: new PngWriter(),
      data: $uuid,
      size: 300,
      margin: 10,
    );

    return $result->getDataUri();
  }

  /**
   * Resolve the best available identifier for a user's QR code.
   */
  private function resolveUserIdentifier($user): string {
    if ($user->hasField('field_crm_id') && !$user->get('field_crm_id')->isEmpty()) {
      return (string) $user->get('field_crm_id')->value;
    }

    if (method_exists($user, 'uuid') && !empty($user->uuid())) {
      return (string) $user->uuid();
    }

    return (string) $user->id();
  }

}
