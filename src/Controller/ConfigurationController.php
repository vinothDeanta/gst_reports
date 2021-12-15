<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;



class ConfigurationController extends AbstractController
{
    /**
     * @Route("/", name="configuration")
     */
    public function index()
    {
        return $this->render('configuration/index.html.twig', [
            'controller_name' => 'ConfigurationController',
        ]);
    }

    /**
     * @Route("/call/upload/spreadsheet", name="uploadSpreadSheet", methods={ "POST" })
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function uploadSpreadSheet(Request $request)
    {   
        //print_r($_FILES['test']);
        return new JsonResponse($_FILES['expense_sheet']);
        
    }
}

