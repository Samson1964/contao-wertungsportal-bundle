<?php

namespace Schachbulle\ContaoWertungsportalBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Schachbulle\ContaoWertungsportalBundle\ContaoWertungsportalBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function getBundles(ParserInterface $parser)
	{
		return [
			BundleConfig::create(ContaoWertungsportalBundle::class)
				->setLoadAfter([ContaoCoreBundle::class]),
		];
	}

	/**
	 * Lädt die Routen des Bundles — zurzeit die öffentliche Schnittstelle für
	 * Vereinslisten (/wertungsportal-api/vereinsliste).
	 *
	 * Ohne diese Methode wird die routing.yml nicht eingelesen und die Adresse
	 * landet auf der 404-Seite von Contao.
	 *
	 * ACHTUNG: Der erste Parameter ist ein LoaderResolverInterface, KEIN
	 * LoaderInterface — mit der falschen Angabe verweigert PHP schon das Laden
	 * der Klasse, und weil das ContaoManager-Plugin bei jedem Aufruf geladen
	 * wird, steht damit die ganze Seite.
	 *
	 * {@inheritdoc}
	 */
	public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
	{
		$datei = __DIR__.'/../Resources/config/routing.yml';

		return $resolver->resolve($datei)->load($datei);
	}
}
