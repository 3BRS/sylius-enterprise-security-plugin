<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractMagicLinkRequestController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\MagicLinkRequestType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminMagicLinkRequestHandlerInterface;
use Twig\Environment;

class MagicLinkRequestController extends AbstractMagicLinkRequestController implements MagicLinkRequestControllerInterface
{
    public function __construct(
        protected AdminMagicLinkRequestHandlerInterface $handler,
        protected FormFactoryInterface $formFactory,
        RouterInterface $router,
        Environment $twig,
        bool $enabled,
    ) {
        parent::__construct($router, $twig, $enabled);
    }

    protected function createForm(): FormInterface
    {
        return $this->formFactory->create(MagicLinkRequestType::class);
    }

    protected function dispatchFromForm(FormInterface $form): void
    {
        /** @var array{email: string} $data */
        $data = $form->getData();
        $this->handler->request($data['email']);
    }

    protected function getRedirectRoute(): string
    {
        return 'three_brs_admin_magic_link_request';
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/MagicLink/request.html.twig';
    }
}
