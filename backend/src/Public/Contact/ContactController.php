<?php

declare(strict_types=1);

namespace App\Public\Contact;

use App\Shared\Http\ClientIp;
use App\Shared\Http\Input;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RequestAttributes;
use App\Shared\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ContactController
{
    public function __construct(
        private readonly ContactMessageService $messages,
        private readonly JsonResponder $json,
        private readonly Translator $translator,
    ) {
    }

    public function send(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $in = Input::fromRequest($request);
        $name = $in->requiredString('name', 100);
        $email = $in->email();
        $message = $in->requiredString('message', 5000, 10);
        $in->secret('turnstile_token', 2048);
        $locale = $in->optionalLocale() ?? self::locale($request);

        $unknownFields = array_diff($in->keys(), ['name', 'email', 'message', 'turnstile_token', 'locale']);
        foreach ($unknownFields as $field) {
            $in->addError($field, 'validation.invalid');
        }
        $in->validate();

        $this->messages->send(
            new ContactMessage($name, $email, $message, ClientIp::toBinary(ClientIp::of($request))),
            $locale,
        );

        return $this->json->created($response, [
            'message' => $this->translator->trans('public.pages.contact.success', [], $locale),
        ]);
    }

    private static function locale(ServerRequestInterface $request): string
    {
        $locale = $request->getAttribute(RequestAttributes::LOCALE);
        if (Translator::isSupported($locale)) {
            return $locale;
        }

        return Translator::negotiate($request->getHeaderLine('Accept-Language')) ?? Translator::DEFAULT;
    }
}
