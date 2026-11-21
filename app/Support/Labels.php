<?php

namespace App\Support;

use App\Models\Document;
use Carbon\CarbonInterface;

class Labels
{
    /** @return array{0: string, 1: string, 2: string} etiqueta, clase de badge, ícono */
    public static function document(?string $status): array
    {
        return match ($status) {
            Document::STATUS_PENDING_ASSOCIATION => ['Por asociar', 'badge-neutral', 'link_off'],
            Document::STATUS_PENDING_REVIEW => ['En revisión admin.', 'badge-warning', 'fact_check'],
            Document::STATUS_READY => ['Listo para publicación', 'badge-info', 'pending_actions'],
            Document::STATUS_PUBLISHED => ['Publicado', 'badge-success', 'check_circle'],
            Document::STATUS_REJECTED => ['Rechazado', 'badge-error', 'cancel'],
            Document::STATUS_REVOKED => ['Revocado', 'badge-error', 'block'],
            default => ['Sin documento', 'badge-neutral', 'hourglass_empty'],
        };
    }

    /** @return array{0: string, 1: string, 2: string} */
    public static function notification(?string $status): array
    {
        return match ($status) {
            'pending' => ['Pendiente', 'badge-neutral', 'schedule'],
            'accepted' => ['Aceptado por transporte', 'badge-info', 'outgoing_mail'],
            'delivered' => ['Entregado', 'badge-success', 'mark_email_read'],
            'failed' => ['Error de envío', 'badge-error', 'error'],
            'disabled' => ['Canal deshabilitado', 'badge-neutral', 'notifications_off'],
            'unknown' => ['Desconocido', 'badge-neutral', 'help'],
            default => ['Sin enviar', 'badge-neutral', 'remove'],
        };
    }

    /** @return array{0: string, 1: string} etiqueta, clase de badge */
    public static function result(string $result): array
    {
        return match ($result) {
            'ok' => ['Correcto', 'badge-success'],
            'accepted' => ['Aceptado', 'badge-info'],
            'delivered' => ['Entregado', 'badge-success'],
            'denied', 'rejected' => ['Denegado', 'badge-error'],
            'throttled' => ['Bloqueado por intentos', 'badge-error'],
            'failed', 'error', 'integrity_failed' => ['Error', 'badge-error'],
            'disabled' => ['Canal deshabilitado', 'badge-neutral'],
            'pending' => ['Pendiente', 'badge-warning'],
            default => [$result, 'badge-neutral'],
        };
    }

    public static function action(string $action): string
    {
        return [
            'document.uploaded' => 'Documento recibido',
            'study.registered' => 'Estudio registrado desde el formato',
            'study.form_folio_exists' => 'Folio de formato ya existente: queda por asociar',
            'contact.verified' => 'Contacto confirmado por personal',
            'document.upload_rejected' => 'Carga rechazada por validación',
            'document.associated' => 'Documento asociado a estudio',
            'document.reviewed' => 'Revisión administrativa confirmada',
            'document.rejected' => 'Documento rechazado',
            'document.published' => 'Documento publicado',
            'document.publish_denied' => 'Publicación denegada (sin facultad)',
            'document.publish_blocked' => 'Publicación bloqueada (integridad)',
            'document.revoked' => 'Publicación revocada',
            'document.staff_viewed' => 'Vista previa interna',
            'document.viewed' => 'Consulta en pantalla (paciente)',
            'document.downloaded' => 'Descarga (paciente)',
            'document.access_denied' => 'Acceso a documento denegado',
            'notification.attempt' => 'Intento de aviso',
            'notification.dispatch' => 'Error al despachar avisos',
            'notification.retry_denied' => 'Reintento de aviso denegado',
            'document.upload_denied' => 'Carga denegada (sin facultad)',
            'document.associate_denied' => 'Asociación denegada (sin facultad)',
            'document.review_denied' => 'Revisión denegada (sin facultad)',
            'document.reject_denied' => 'Rechazo denegado (sin facultad)',
            'document.revoke_denied' => 'Revocación denegada (sin facultad)',
            'document.file_denied' => 'Vista de archivo denegada (sin facultad)',
            'contact.verify_denied' => 'Confirmación de contacto denegada',
            'user.created' => 'Cuenta de personal creada',
            'user.updated' => 'Cuenta de personal modificada',
            'user.password_reset' => 'Contraseña restablecida',
            'user.disabled' => 'Cuenta desactivada',
            'user.enabled' => 'Cuenta reactivada',
            'user.manage_denied' => 'Gestión de usuarios denegada',
            'access.code_issued' => 'Código temporal emitido',
            'access.redeemed' => 'Código canjeado',
            'access.failed' => 'Intento de acceso no válido',
            'staff.login' => 'Inicio de sesión de personal',
            'staff.logout' => 'Cierre de sesión de personal',
        ][$action] ?? $action;
    }

    public static function dt(?CarbonInterface $date, string $format = 'd/m/Y H:i'): string
    {
        return $date ? $date->copy()->timezone(config('eira.display_timezone'))->format($format) : '—';
    }
}
