<?php

namespace App\Application\Output;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class Output
 *
 * @package App\Application\Output
 */
final class Output
{
    /**
     * Success
     *
     * @param array $response
     *
     * @return JsonResponse
     */
    static public function throwSuccess(array $response = [])
    {
        return new JsonResponse($response, 200);
    }

    /**
     * No Content
     *
     * @param array $response
     *
     * @return JsonResponse
     */
    static public function throwNoContent(array $response = [])
    {
        return new JsonResponse($response, 204);
    }

    /**
     * Operation not completed
     *
     * @param string $msg
     *
     * @return JsonResponse
     */
    static public function throwNotAllowed(string $msg = '')
    {
        return new JsonResponse(array(
            "error" => [
                "code" => 405,
                "message" => $msg,
            ],
        ), 405);
    }

    /**
     * Bad Request
     *
     * @param string $msg
     *
     * @return JsonResponse
     */
    static public function throwErrorBadRequest(string $msg = '')
    {
        return new JsonResponse(array(
            "code" => 400,
            "message" => $msg,
        ), 400);
    }

    /**
     * Unauthorized
     *
     * @param string $msg
     *
     * @return JsonResponse
     */
    static public function throwErrorUnauthorized(string $msg = '')
    {
        return new JsonResponse(array(
            "code" => 401,
            "message" => $msg,
        ), 401);
    }

    /**
     * Not Acceptable
     *
     * @param string $msg
     *
     * @return JsonResponse
     */
    static public function throwErrorNotAcceptable(string $msg = '')
    {
        return new JsonResponse(array(
            "code" => 406,
            "message" => $msg,
        ), 406);
    }

    /**
     * General Error
     *
     * @param string $msg
     *
     * @return JsonResponse
     */
    static public function throwError(string $msg = '')
    {
        return new JsonResponse(array(
            "code" => 500,
            "message" => $msg,
        ), 500);
    }

    /**
     * _ONLY FOR DEBUG PURPOSES
     *
     * @param $param
     * @param bool $die
     *
     * @return bool
     */
    static public function debug($param, $die = true)
    {
        echo '<pre>';
        print_r($param);
        echo '</pre>';

        if ($die) {
            die('die!');
        }
        return true;
    }

    /**
     * _ONLY FOR DEBUG PURPOSES
     *
     * @param array $response
     *
     * @return JsonResponse
     */
    static public function throwDebug($msg)
    {
        return new JsonResponse(array("debug" => $msg), 200);
    }
}