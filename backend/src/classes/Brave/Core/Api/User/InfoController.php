<?php
namespace Brave\Core\Api\User;

use Slim\Http\Response;
use Brave\Core\Service\UserAuthService;

class InfoController
{

    /**
     * @SWG\Get(
     *     path="/user/info",
     *     summary="Show current logged in user information. Needs role: user",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The user information",
     *         @SWG\Schema(ref="#/definitions/User")
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="If not authenticated"
     *     )
     * )
     */
    public function __invoke(Response $response, UserAuthService $uas)
    {
        return $response->withJson($uas->getUser());
    }
}
