<?php
declare(strict_types=1);

namespace CakeGraphQL\Controller;

use Cake\Controller\Controller;
use Cake\Http\Response;

final class GraphqlController extends Controller
{
    public function execute(): Response
    {
        $this->autoRender = false;

        return $this->response
            ->withType('application/json')
            ->withStatus(400)
            ->withStringBody('{"errors":[{"message":"GraphQL request was not handled by CakeGraphQL middleware."}]}');
    }
}
