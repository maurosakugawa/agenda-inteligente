<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Contacts;

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Request;

final class ContactController
{
    public function __construct(
        private ContactLister $lister,
        private ContactCreator $creator,
        private ContactUpdater $updater,
        private ContactDeleter $deleter
    ) {
    }

    public function list(
        Request $request
    ): JsonResponse {
        $userId =
            $this->authenticatedUserId(
                $request
            );

        if ($userId === null) {
            return $this->unauthorized();
        }

        return new JsonResponse(
            $this->lister->list(
                $userId
            ),
            200
        );
    }

    public function create(
        Request $request
    ): JsonResponse {
        $userId =
            $this->authenticatedUserId(
                $request
            );

        if ($userId === null) {
            return $this->unauthorized();
        }

        $input =
            $this->contactInput(
                $request->json()
            );

        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $contact =
                $this->creator->create(
                    $userId,
                    $input['name'],
                    $input['phone'],
                    $input['email'],
                    $input['cep'],
                    $input['logradouro'],
                    $input['numero'],
                    $input['bairro'],
                    $input['cidade'],
                    $input['uf']
                );
        } catch (
            InvalidContactInputException $exception
        ) {
            return $this->invalidInput(
                $exception->getMessage()
            );
        }

        return new JsonResponse(
            $contact,
            201
        );
    }

    public function update(
        Request $request
    ): JsonResponse {
        $userId =
            $this->authenticatedUserId(
                $request
            );

        if ($userId === null) {
            return $this->unauthorized();
        }

        $contactId =
            $this->contactId(
                $request
            );

        if ($contactId === null) {
            return $this->invalidContactId();
        }

        $input =
            $this->contactInput(
                $request->json()
            );

        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $contact =
                $this->updater->update(
                    $contactId,
                    $userId,
                    $input['name'],
                    $input['phone'],
                    $input['email'],
                    $input['cep'],
                    $input['logradouro'],
                    $input['numero'],
                    $input['bairro'],
                    $input['cidade'],
                    $input['uf']
                );
        } catch (
            InvalidContactInputException $exception
        ) {
            return $this->invalidInput(
                $exception->getMessage()
            );
        } catch (
            ContactNotFoundException
        ) {
            return $this->notFound();
        }

        return new JsonResponse(
            $contact,
            200
        );
    }

    public function delete(
        Request $request
    ): JsonResponse {
        $userId =
            $this->authenticatedUserId(
                $request
            );

        if ($userId === null) {
            return $this->unauthorized();
        }

        $contactId =
            $this->contactId(
                $request
            );

        if ($contactId === null) {
            return $this->invalidContactId();
        }

        try {
            $this->deleter->delete(
                $contactId,
                $userId
            );
        } catch (
            ContactNotFoundException
        ) {
            return $this->notFound();
        }

        return new JsonResponse(
            [
                'message' =>
                    'Contato removido',
            ],
            200
        );
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     name:string,
     *     phone:?string,
     *     email:?string,
     *     cep:?string,
     *     logradouro:?string,
     *     numero:?string,
     *     bairro:?string,
     *     cidade:?string,
     *     uf:?string
     * }|JsonResponse
     */
    private function contactInput(
        array $input
    ): array|JsonResponse {
        $name =
            $input['name']
            ?? null;

        if (!is_string($name)) {
            return $this->invalidInput(
                'Nome do contato é obrigatório.'
            );
        }

        $optionalFields = [
            'phone' => 'Telefone',
            'email' => 'E-mail',
            'cep' => 'CEP',
            'logradouro' => 'Logradouro',
            'numero' => 'Número',
            'bairro' => 'Bairro',
            'cidade' => 'Cidade',
            'uf' => 'UF',
        ];

        foreach (
            $optionalFields as $field => $label
        ) {
            if (
                !array_key_exists(
                    $field,
                    $input
                )
            ) {
                continue;
            }

            $value =
                $input[$field];

            if (
                $value !== null
                && !is_string($value)
            ) {
                return $this->invalidInput(
                    "{$label} deve ser texto ou null."
                );
            }
        }

        return [
            'name' => $name,
            'phone' =>
                $this->nullableString(
                    $input,
                    'phone'
                ),
            'email' =>
                $this->nullableString(
                    $input,
                    'email'
                ),
            'cep' =>
                $this->nullableString(
                    $input,
                    'cep'
                ),
            'logradouro' =>
                $this->nullableString(
                    $input,
                    'logradouro'
                ),
            'numero' =>
                $this->nullableString(
                    $input,
                    'numero'
                ),
            'bairro' =>
                $this->nullableString(
                    $input,
                    'bairro'
                ),
            'cidade' =>
                $this->nullableString(
                    $input,
                    'cidade'
                ),
            'uf' =>
                $this->nullableString(
                    $input,
                    'uf'
                ),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function nullableString(
        array $input,
        string $field
    ): ?string {
        $value =
            $input[$field]
            ?? null;

        return is_string($value)
            ? $value
            : null;
    }

    private function authenticatedUserId(
        Request $request
    ): ?int {
        $userId =
            $request->attribute(
                'authenticated_user_id'
            );

        if (
            !is_int($userId)
            || $userId <= 0
        ) {
            return null;
        }

        return $userId;
    }

    private function contactId(
        Request $request
    ): ?int {
        $value =
            $request->routeParam(
                'id'
            );

        if (
            $value === null
            || preg_match(
                '/^[1-9][0-9]*$/D',
                $value
            ) !== 1
        ) {
            return null;
        }

        $id =
            filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

        return is_int($id)
            ? $id
            : null;
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(
            [
                'error' =>
                    'Autenticação necessária',
            ],
            401
        );
    }

    private function invalidContactId(): JsonResponse
    {
        return new JsonResponse(
            [
                'error' =>
                    'ID de contato inválido.',
            ],
            400
        );
    }

    private function invalidInput(
        string $message
    ): JsonResponse {
        return new JsonResponse(
            [
                'error' =>
                    $message,
            ],
            400
        );
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(
            [
                'error' =>
                    'Contato não encontrado',
            ],
            404
        );
    }
}
