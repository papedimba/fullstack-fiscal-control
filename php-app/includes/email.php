<?php
require_once __DIR__ . '/../config/config.php';

class Mailer {
    public static function send(string $to, string $toName, string $subject, string $htmlBody): bool {
        if (!MAIL_ENABLED) return false;

        $vendorPath = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($vendorPath)) return self::fallback($to, $subject, $htmlBody);

        require_once $vendorPath;
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) return self::fallback($to, $subject, $htmlBody);

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);
            $mail->send();
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public static function sendResetLink(string $to, string $name, string $token): bool {
        $url  = APP_URL . '/index.php?reset_token=' . $token;
        $body = "
        <p>Bonjour <strong>$name</strong>,</p>
        <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
        <p><a href=\"$url\" style=\"background:#1E3A5F;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;\">Réinitialiser mon mot de passe</a></p>
        <p>Ce lien expire dans <strong>1 heure</strong>.</p>
        <p>Si vous n'avez pas effectué cette demande, ignorez cet email.</p>
        <hr><p style=\"color:#888;font-size:12px;\">Direction des Vérifications Fiscales Nationale</p>";
        return self::send($to, $name, 'Réinitialisation de mot de passe — Contrôle Fiscal', $body);
    }

    public static function sendRetardAlert(string $to, string $name, string $numeroDossier, string $typeEtape): bool {
        $body = "
        <p>Bonjour <strong>$name</strong>,</p>
        <p>Une étape du dossier <strong>$numeroDossier</strong> est en <strong style=\"color:#DC2626;\">retard</strong>.</p>
        <p>Étape concernée : <strong>$typeEtape</strong></p>
        <p>Veuillez vous connecter à l'application pour mettre à jour ce dossier.</p>
        <hr><p style=\"color:#888;font-size:12px;\">Direction des Vérifications Fiscales Nationale</p>";
        return self::send($to, $name, "Retard détecté — Dossier $numeroDossier", $body);
    }

    private static function fallback(string $to, string $subject, string $htmlBody): bool {
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n"
                 . "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        return @mail($to, $subject, $htmlBody, $headers);
    }
}
