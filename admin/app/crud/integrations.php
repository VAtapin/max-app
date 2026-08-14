<?php

function integration_callback_secret(): string
{
    try {
        $random = bin2hex(random_bytes(12));
    } catch (Throwable) {
        $random = str_replace('.', '', uniqid('', true));
    }

    return 'swpro_vk_' . $random;
}
