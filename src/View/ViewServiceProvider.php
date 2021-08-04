<?php


	declare( strict_types = 1 );


	namespace Snicco\View;

    use Snicco\Contracts\ServiceProvider;
    use Snicco\Contracts\ViewEngineInterface;
    use Snicco\Contracts\ViewFactoryInterface;
    use Snicco\Factories\ViewComposerFactory;

    class ViewServiceProvider extends ServiceProvider
    {

        public function register() : void
        {

            $this->extendViews($this->config->get('app.package_root').DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views');

            $this->bindMethodField();

            $this->bindGlobalContext();

            $this->bindViewServiceImplementation();

            $this->bindViewFactoryInterface();

			$this->bindPhpViewEngine();

			$this->bindViewEngineInterface();

			$this->bindViewComposerCollection();

		}

		public function bootstrap() : void {

		    $context = $this->container->make(GlobalContext::class);
		    $context->add('__view', function () {
		        return $this->container->make(ViewFactory::class);
            });

		}

        private function bindGlobalContext()
        {
            $this->container->instance(GlobalContext::class, new GlobalContext());

        }

        private function bindViewFactoryInterface() : void
        {

            $this->container->singleton(ViewFactoryInterface::class, function () {

                return $this->container->make(ViewFactory::class);

            });
        }

        private function bindViewServiceImplementation() : void
        {

            $this->container->singleton(ViewFactory::class, function () {

                return new ViewFactory(
                    $this->container->make(ViewEngineInterface::class),
                    $this->container->make(ViewComposerCollection::class),
                    $this->container->make(GlobalContext::class)

                );

            });
        }

        private function bindPhpViewEngine() : void
        {

            $this->container->singleton(PhpViewEngine::class, function () {

                return new PhpViewEngine(
                    new PhpViewFinder($this->config->get('view.paths', []))
                );

            });
        }

        private function bindViewEngineInterface() : void
        {

            $this->container->singleton(ViewEngineInterface::class, function () {

                return $this->container->make(PhpViewEngine::class);

            });
        }

        private function bindViewComposerCollection() : void
        {

            $this->container->singleton(ViewComposerCollection::class, function () {

                return new ViewComposerCollection(
                    $this->container->make(ViewComposerFactory::class),
                );

            });
        }

        private function bindMethodField()
        {
            $this->container->singleton(MethodField::class, function () {
                return new MethodField($this->appKey());
            });
        }

    }
