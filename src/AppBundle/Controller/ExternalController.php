<?php

namespace AppBundle\Controller;

use AppBundle\Alexa\Request\CachedCertificate;
use AppBundle\Alexa\Skill\HomeSkill;
use AppBundle\Service\NetworkPresenceService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/external")
 */
class ExternalController extends Controller
{    
    /**
     * @Route("/alexa", name="external-alexa")
     */
    public function alexaAction(Request $request)
    {
        $skill = new HomeSkill($this->container);
        return $this->json($skill->handle($request));
    }
}
