<?php

declare(strict_types=1);

namespace MOL\REST;

use Throwable;

final class Responses
{
    public static function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ApiException $error) {
            $data = array_merge($error->details(), array('status' => $error->status()));
            if (429 === $error->status()) {
                $response = new \WP_REST_Response(array(
                    'code' => $error->errorCode(),
                    'message' => $error->getMessage(),
                    'data' => $data,
                ), 429);
                if (isset($data['retry_after'])) {
                    $response->header('Retry-After', (string) $data['retry_after']);
                }

                return $response;
            }

            return new \WP_Error($error->errorCode(), $error->getMessage(), $data);
        } catch (Throwable $error) {
            error_log(sprintf('Manga Overlay REST failure: %s', $error->getMessage()));

            return new \WP_Error(
                'mol_internal_error',
                'The request could not be completed.',
                array('status' => 500)
            );
        }
    }
}
