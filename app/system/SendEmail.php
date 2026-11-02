<?php

namespace bng\System;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class SendEmail
{
    //============================================================
    public function sendEmail(array $data, string $subject, string $body)
    {
        $mail = new PHPMailer(true);

        try {

            // IMPORTANT:
            // Uncomment to add server options from your email account provider
            //Server settings
            /*
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      
            $mail->isSMTP();                                           
            $mail->Host       = EMAIL_HOST;                    
            $mail->SMTPAuth   = true;                                  
            $mail->Username   = EMAIL_USERNAME;                    
            $mail->Password   = EMAIL_PASSWORD;                              
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            
            $mail->Port       = EMAIL_PORT; 
            */

            //Recipients
            $mail->setFrom(EMAIL_FROM);
            $mail->addAddress($data['to']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $this->$body($data);

            $mail->send();

            return [
                'status' => 'success',
                'message' => 'Email enviado com sucesso'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $mail->ErrorInfo,
            ];
        }
    }

    //=========================================================================
    private function email_body_new_agent(array $data)
    {
        $html = '<p>Para concluir o processo de registro de agente, clique no link abaixo:</p>';
        $html .= '<a href="' . $data['link'] . '">Clique aqui para concluir</a>';

        return $html;
    }

    //=========================================================================
    private function codigo_recuperar_password(array $data)
    {
        $html = '<p>Para concluir o processo de recuperação de senha, clique no link abaixo:</p>';
        $html .= "<h3>{$data['code']}</h3>";

        return $html;
    }
}
