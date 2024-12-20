<?php

namespace App\Controllers;

use App\Models\MeioPagamento;
use App\Models\Produto;
use App\Models\Venda;
use App\Models\AgrupadorVenda;
use App\Repositories\VendasEmSessaoRepository;
use App\Rules\AcessoAoTipoDePdv;
use App\Rules\Logged;
use System\Controller\Controller;
use System\Get\Get;
use System\Post\Post;
use System\Session\Session;

class PdvDiferencialController extends Controller
{
    protected $post;
    protected $get;
    protected $layout;
    protected $idEmpresa;
    protected $idUsuario;
    protected $idPerfilUsuarioLogado;
    protected $vendasEmSessaoRepository;

    public function __construct()
    {
        parent::__construct();
        $this->layout = 'default';

        $this->post = new Post();
        $this->get = new Get();
        $this->idEmpresa = Session::get('idEmpresa');
        $this->idUsuario = Session::get('idUsuario');
        $this->idPerfilUsuarioLogado = Session::get('idPerfil');

        $this->vendasEmSessaoRepository = new VendasEmSessaoRepository();

        $logged = new Logged();
        $logged->isValid();

        $acessoAoTipoDePdv = new AcessoAoTipoDePdv();
        $acessoAoTipoDePdv->validate();
    }

    public function index()
    {
        $meioPagamento = new MeioPagamento();
        $meiosPagamentos = $meioPagamento->all();

        $produto = new Produto();
        $produtos = $produto->produtosNoPdv($this->idEmpresa);

        $this->view('pdv/diferencial', $this->layout,
            compact(
                'meiosPagamentos',
                'produtos'
            ));
    }

    public function saveVendasViaSession()
    {
        if (!isset($_SESSION['venda']) ||empty($_SESSION['venda'])) {
            return;
        }

        $status = false;
        $meioDePagamento = $this->post->data()->id_meio_pagamento;
        $dataCompensacao = '0000-00-00';

        # só adiciona caso seja um boleto
        if ($meioDePagamento == 4) {
            $dataCompensacao = $this->post->data()->data_compensacao;
        }

        /**
         * Gera um código unico de venda que será usado em todos os registros desse Loop
        */
        $codigoVenda = uniqid(rand(), true).date('s').date('d.m.Y');

        $valorRecebido = formataValorMoedaParaGravacao($this->post->data()->valor_recebido);
        $troco = formataValorMoedaParaGravacao($this->post->data()->troco);

        foreach ($_SESSION['venda'] as $produto) {
            $dados = [
                'id_usuario' => $this->idUsuario,
                'id_meio_pagamento' => $meioDePagamento,
                'data_compensacao' => $dataCompensacao,
                'id_empresa' => $this->idEmpresa,
                'id_produto' => $produto['id'],
                'preco' => $produto['preco'],
                'quantidade' => $produto['quantidade'],
                'valor' => $produto['total'],
                'codigo_venda' => $codigoVenda
            ];

            if ( ! empty($valorRecebido) && ! empty($troco)) {
                $dados['valor_recebido'] = $valorRecebido;
                $dados['troco'] = $troco;
            }

            $venda = new Venda();
            try {
                $venda = $venda->save($dados);
                $status = true;

                $produto = new Produto();
                $produto->decrementaQuantidadeProduto((int) $dados['id_produto'], (int) $dados['quantidade']);

                unset($_SESSION['venda']);

            } catch (\Exception $e) {
                dd($e->getMessage());
            }
        }

        echo json_encode(['status' => $status]);
    }

    public function colocarProdutosNaMesa($idProduto)
    {
        return $this->vendasEmSessaoRepository->colocarProdutosNaMesa($idProduto);
    }

    public function obterProdutosDaMesa($posicaoProduto)
    {
        echo $this->vendasEmSessaoRepository->obterProdutosDaMesa($posicaoProduto);
    }

    public function alterarAquantidadeDeUmProdutoNaMesa($idProduto, $quantidade)
    {
        $produto = new Produto();
        $dadosProduto = $produto->find($idProduto);

        if ($dadosProduto->ativar_quantidade && $quantidade > $dadosProduto->quantidade) {
            echo json_encode(['quantidade_insuficiente' => true, 'unidades' => $dadosProduto->quantidade]);
            return false;
        }

        $this->vendasEmSessaoRepository->alterarAquantidadeDeUmProdutoNaMesa($idProduto, $quantidade);
        echo json_encode(['quantidade_insuficiente' => false]);
    }

    public function retirarProdutoDaMesa($idProduto)
    {
        $this->vendasEmSessaoRepository->retirarProdutoDaMesa($idProduto);
    }

    public function obterValorTotalDosProdutosNaMesa()
    {
        echo $this->vendasEmSessaoRepository->obterValorTotalDosProdutosNaMesa();
    }

    public function calcularTroco($valorRecebido)
    {
        $valorRecebido = out64($valorRecebido);
        $valorRecebido = explode('R$', $valorRecebido);
        if (array_key_exists(1, $valorRecebido)) {
            $valor = $valorRecebido[1];
        } else {
            $valor = $valorRecebido[0];
        }

        echo $this->vendasEmSessaoRepository->calcularTroco(formataValorMoedaParaGravacao($valor));
    }

    public function pesquisarProdutoPorNome($nome = false)
    {
        $nome = utf8_encode(out64($nome));

        $produto = new Produto();
        $produtos = $produto->produtosNoPdv($this->idEmpresa, $nome);

        $this->view('pdv/produtosAvenda', null, compact('produtos'));
    }

    public function pesquisarProdutoPorCodeDeBarra($codigo = false)
    {
        $codigo = utf8_encode(out64($codigo));

        $produto = new Produto();
        $produtos = $produto->produtosNoPdvFiltrarPorCodigoDeBarra($this->idEmpresa, $codigo);

        $this->view('pdv/produtosAvenda', null, compact('produtos'));
    }
    
    public function qrCodePix($valorTotal)
    {
        $accessToken = 'APP_USR-2********';
        $url = "https://api.mercadopago.com/v1/payments";
        
        $data = [
        "transaction_amount" => (float)$valorTotal,
        "payment_method_id" => "pix",
        "description" => "Pagamento via PIX",
        "payer" => [
                "email" => "clientePDV@email.com"
            ]
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $accessToken"
        ]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    
        // Tratando a resposta
        if ($httpCode == 201) {
            $responseArray = json_decode($response, true);
            
            echo json_encode([
                "transaction_id" => $responseArray["id"],
                "base64" => $responseArray["point_of_interaction"]["transaction_data"]["qr_code_base64"]
            ]);
        } else {
            $error = json_decode($response, true);
            echo json_encode([
                "error" => $error["message"] ?? "Erro ao gerar o QR Code PIX"
            ]);
        }
    }
    
    public function statusPix($transactionId)
    {
        $accessToken = 'APP_USR-2********';
        $url = "https://api.mercadopago.com/v1/payments/$transactionId";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken"
        ]);
        
        // Executa a solicitação e captura a resposta
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    
        // Tratando a resposta
        if ($httpCode == 200) {
            $responseArray = json_decode($response, true);
            
            echo json_encode([
                "status" => $responseArray["status"],
                "detail" => $responseArray["status_detail"],
                "id" => $responseArray["id"],
                "transaction_amount" => $responseArray["transaction_amount"]
            ]);
        } else {
            $error = json_decode($response, true);
            echo json_encode([
                "error" => $error["message"] ?? "Erro ao consultar o status do pagamento"
            ]);
        }
    }
}
