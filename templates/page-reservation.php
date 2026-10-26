<?php
/**
 * Gabarit de la page de suivi d'une réservation (/?hs_resa=ID&cle=…) : en-tête et pied du thème autour de reservation-suivi.php.
 * Surchargeable : <thème>/homystay/page-reservation.php
 */
defined( 'ABSPATH' ) || exit;

HS_Single::header();
echo HS_Templates::render( 'reservation-suivi.php', HS_Reservation::$suivi ); // phpcs:ignore WordPress.Security.EscapeOutput
HS_Single::footer();
