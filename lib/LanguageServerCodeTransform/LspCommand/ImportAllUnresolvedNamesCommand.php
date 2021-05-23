<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\LspCommand;

use Amp\Promise;
use Phpactor\CodeTransform\Domain\NameWithByteOffset;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImport\CandidateFinder;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImport\NameCandidate;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\LanguageServer\Core\Command\CommandDispatcher;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\WorseReflection\Core\Reflector\FunctionReflector;
use Phpactor\CodeTransform\Domain\Helper\UnresolvableClassNameFinder;
use function Amp\call;

class ImportAllUnresolvedNamesCommand
{
    public const NAME = 'import_all_unresolved_names';

    /**
     * @var CandidateFinder
     */
    private $candidateFinder;

    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var CommandDispatcher
     */
    private $dispatcher;

    /**
     * @var ClientApi
     */
    private $client;

    public function __construct(
        CandidateFinder $candidateFinder,
        Workspace $workspace,
        CommandDispatcher $dispatcher,
        ClientApi $client
    ) {
        $this->candidateFinder = $candidateFinder;
        $this->workspace = $workspace;
        $this->dispatcher = $dispatcher;
        $this->client = $client;
    }

    public function __invoke(
        string $uri
    ): Promise
    {
        return call(function () {
            $item = $this->workspace->get($uri);
            foreach ($this->candidateFinder->unresolved($item) as $unresolvedName) {
                assert($unresolvedName instanceof NameWithByteOffset);
                $candidates = iterator_to_array($this->candidateFinder->candidatesForUnresolvedName($unresolvedName));
                $candidate = $this->resolveCandidate($candidates);
                if (null === $candidate) {
                    $this->client->window()->showMessage()->warning(sprintf(
                        'Class "%s" has no candidates',
                        $unresolvedName->name()->__toString()
                    ));
                }

                $this->dispatcher->dispatch(ImportNameCommand::NAME, [
                    $uri,
                    $unresolvedName->byteOffset(),
                    $unresolvedName->type(),
                    $candidate->candidateFqn()
                ]);
            }
        });
    }

    private function resolveCandidate(array $candidates): ?NameCandidate
    {
        foreach ($candidates as $candidate) {
            return $candidate;
        }

        return null;
    }
}
