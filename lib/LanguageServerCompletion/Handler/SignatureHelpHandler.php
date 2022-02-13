<?php

namespace Phpactor\Extension\LanguageServerCompletion\Handler;

use Amp\Promise;
use Phpactor\Extension\AbstractHandler;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\LanguageServerProtocol\ClientCapabilities;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServerProtocol\SignatureHelp;
use Phpactor\LanguageServerProtocol\SignatureHelpOptions;
use Phpactor\LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\Completion\Core\Exception\CouldNotHelpWithSignature;
use Phpactor\Completion\Core\SignatureHelper;
use Phpactor\Extension\LanguageServerCompletion\Util\PhpactorToLspSignature;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\TextDocument\TextDocumentBuilder;

class SignatureHelpHandler extends AbstractHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var SignatureHelper
     */
    private $helper;

    public function __construct(Workspace $workspace, SignatureHelper $helper, ClientCapabilities $clientCapabilities)
    {
        $this->workspace = $workspace;
        $this->helper = $helper;
        parent::__construct($clientCapabilities);
    }

    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        return [
            'textDocument/signatureHelp' => 'signatureHelp'
        ];
    }

    public function signatureHelp(
        TextDocumentIdentifier $textDocument,
        Position $position
    ): Promise {
        return \Amp\call(function () use ($textDocument, $position) {
            $textDocument = $this->workspace->get($textDocument->uri);

            $languageId = $textDocument->languageId ?: 'php';

            try {
                return PhpactorToLspSignature::toLspSignatureHelp($this->helper->signatureHelp(
                    TextDocumentBuilder::create($textDocument->text)->language($languageId)->uri($textDocument->uri)->build(),
                    PositionConverter::positionToByteOffset($position, $textDocument->text)
                ));
            } catch (CouldNotHelpWithSignature $couldNotHelp) {
                return null;
            }
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->signatureHelpProvider = (null !== $this->clientCapabilities->textDocument->signatureHelp)
            ? new SignatureHelpOptions(['(', ','])
            : null;
    }
}
