<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Exception\DuplicateContactEmailException;
use App\Form\ContactType;
use App\Service\ContactService;
use App\Service\RecaptchaVerifierService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contacts', name: 'contact_')]
final class ContactController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContactService $contactService,
        RecaptchaVerifierService $recaptchaVerifierService
    ): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $recaptchaToken = (string) $form->get('recaptchaToken')->getData();
            $verification = $recaptchaVerifierService->verifyV3Token($recaptchaToken, $request->getClientIp());

            if (!$verification->isValid) {
                $form->addError(new FormError('Captcha verification failed. Please try again.'));

                return $this->renderContactPage($form, $contactService, $recaptchaVerifierService);
            }

            try {
                $contactService->create($contact);
            } catch (DuplicateContactEmailException) {
                $form->get('email')->addError(new FormError('This email address is already in use.'));

                return $this->renderContactPage($form, $contactService, $recaptchaVerifierService);
            }

            $this->addFlash('success', 'Contact created successfully.');

            return $this->redirectToRoute('contact_index');
        }

        return $this->renderContactPage($form, $contactService, $recaptchaVerifierService);
    }

    private function renderContactPage(
        FormInterface $form,
        ContactService $contactService,
        RecaptchaVerifierService $recaptchaVerifierService
    ): Response {
        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView(),
            'contacts' => $contactService->listAll(),
            'recaptchaEnabled' => $recaptchaVerifierService->isEnabled(),
            'recaptchaSiteKey' => $recaptchaVerifierService->getSiteKey(),
            'recaptchaAction' => $recaptchaVerifierService->getAction(),
        ]);
    }
}
