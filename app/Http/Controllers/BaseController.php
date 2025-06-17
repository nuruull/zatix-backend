<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Dokumentasi API",
 *      description="Lorem Ipsum",
 *      @OA\Contact(
 *          email="nurulhdyh1704@gmail.com"
 *      ),
 *      @OA\License(
 *          name="Apache 2.0",
 *          url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *      )
 * )
 *
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="Demo API Server"
 * )
 */
class BaseController extends Controller
{
    protected function sendResponse($result, $message, $code = 200)
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $result
        ];

        return Response($response, $code);
    }

    protected function sendError($error, $errorMessage = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error
        ];

        if (!empty($errorMessage)) {
            $response['errors'] = $errorMessage;
        }

        return Response($response, $code);
    }
}
