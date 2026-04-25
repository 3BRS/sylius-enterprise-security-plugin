<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\MagicLinkRequestType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminMagicLinkRequestHandlerInterface;
use Twig\Environment;

class MagicLinkRequestController implements MagicLinkRequestControllerInterface
{
    use FlashHelperTrait;

    public function __construct(
        protected AdminMagicLinkRequestHandlerInterface $handler,
        protected FormFactoryInterface $formFactory,
        protected RouterInterface $router,
        protected Environment $twig,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $form = $this->formFactory->create(MagicLinkRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string} $data */
            $data = $form->getData();
            $this->handler->request($data['email']);

            $this->addFlashMessage($request, 'success', 'three_brs.ui.magic_link.request_sent');

            return new RedirectResponse($this->router->generate('three_brs_admin_magic_link_request'));
        }

        return new Response($this->twig->render('@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/MagicLink/request.html.twig', [
            'form' => $form->createView(),
        ]));
    }
}
