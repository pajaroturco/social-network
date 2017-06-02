<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

use BackendBundle\Entity\User;
use BackendBundle\Entity\PrivateMessage;
use AppBundle\Form\PrivateMessageType;

class PrivateMessageController extends Controller {

    private $session;

    public function __construct() {
        $this->session = new Session();
    }

    public function indexAction(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        $private_message = new PrivateMessage();
        $form = $this->createForm(PrivateMessageType::class, $private_message, array(
            'empty_data' => $user
        ));

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
          if ($form->isValid()) {

            //upload Image
            $file = $form['image']->getData();

            if (!empty($file) && $file != null) {
              $ext = $file->guessExtension();

              if ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png' || $ext == 'gif') {
                  $file_name = $user->getId().time().".".$ext;
                  $file->move("uploads/messages/images", $file_name);
                  $private_message->setImage($file_name);

              }else {
                  $private_message->setImage(null);
              }
            }else {
              $private_message->setImage(null);
            }

            //upload document
            $doc = $form['file']->getData();

            if (!empty($doc) && $doc != null) {
              $ext = $doc->guessExtension();

              if ($ext == 'pdf') {
                  $file_name = $user->getId().time().".".$ext;
                  $doc->move("uploads/messages/documents", $file_name);
                  $private_message->setFile($file_name);

              }else {
                  $private_message->setFile(null);
              }
            }else {
              $private_message->setFile(null);
            }

            $private_message->setEmitter($user);
            $private_message->setCreatedAt(new \DateTime("now"));
            $private_message->setReaded(0);

            $em->persist($private_message);
            $flush = $em->flush();

            if ($flush == null) {
              $status = 'El mensaje se envió correctamente';
            }else {
              $status = 'El mensaje no se ha enviado';
            }

          }else {
            $status = 'No se pudo enviar el mensaje';
          }

          $this->session->getFlashBag()->add('status', $status);
          return $this->redirectToRoute('private_message_index');
        }

        $private_messages = $this->getPrivateMessages($request, 'received');
        $this->setReaded($em, $user);

        return $this->render('AppBundle:PrivateMessage:index.html.twig', array(
                'form' => $form->createView(),
                'pagination' => $private_messages
        ));
    }

    public function sendedAction(Request $request){
        $private_messages = $this->getPrivateMessages($request, 'sended');

        return $this->render('AppBundle:PrivateMessage:sended.html.twig', [
          'pagination' => $private_messages
        ]);
    }

    public function getPrivateMessages($request, $type = null){
      $em = $this->getDoctrine()->getManager();
      $user = $this->getUser();
      $user_id = $user->getId();

      if ($type == 'sended' || $type == null) {
        $dql = "SELECT p FROM BackendBundle:PrivateMessage p WHERE"
                ." p.emitter = $user_id ORDER BY p.id DESC";
      }else {
        $dql = "SELECT p FROM BackendBundle:PrivateMessage p WHERE"
                ." p.receiver = $user_id ORDER BY p.id DESC";
      }

      $query = $em->createQuery($dql);

      $paginator = $this->get('knp_paginator');
      $pagination = $paginator->paginate(
                    $query,
                    $request->query->getInt('page',1),
                    5
                  );

      return $pagination;

    }

    public function notReadedAction(){
      $em = $this->getDoctrine()->getManager();
      $user = $this->getUser();

      $pm_repo = $em->getRepository('BackendBundle:PrivateMessage');
      $count_not_readed = count($pm_repo->findBy(array(
        'receiver' => $user,
        'readed' => 0
      )));

      return new Response($count_not_readed);
    }

    private function setReaded($em, $user){
      $pm_repo = $em->getRepository('BackendBundle:PrivateMessage');
      $messages = $pm_repo->findBy(array(
        'receiver' => $user,
        'readed' => 0
      ));

      foreach ($messages as $message) {
        $message->setReaded(1);
        $em->persist($message);
      }
      $flush = $em->flush();

      if ($flush == null) {
        $result = true;
      }else {
        $result = false;
      }

      return $result;
    }

  }
