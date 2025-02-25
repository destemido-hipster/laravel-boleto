<?php

namespace Eduardokum\LaravelBoleto\Boleto\Banco;

use Eduardokum\LaravelBoleto\Util;
use Eduardokum\LaravelBoleto\CalculoDV;
use Eduardokum\LaravelBoleto\Boleto\AbstractBoleto;
use Eduardokum\LaravelBoleto\Exception\ValidationException;
use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;

class Safra extends AbstractBoleto implements BoletoContract
{
    public function __construct(array $params = [])
    {
        parent::__construct($params);
    }

    /**
     * Código do banco
     *
     * @var string
     */
    protected $codigoBanco = self::COD_BANCO_SAFRA;

    /**
     * Define as carteiras disponíveis para este banco
     *
     * @var array
     */
    protected $carteiras = ['02'];



    /**
     * Espécie do documento, código para remessa 400
     *
     * @var string
     */
    protected $especiesCodigo400 = [
        'DM'  => '01',
        'NP'  => '02',
        'AP'  => '03',
        'RC'  => '05',
        'DP'  => '06',
        'LC'  => '07',
        'BDP' => '08',
        'BCC' => '19',
    ];

    protected $iof = [
        "isento" => 90,
        "aliquota_1" => 91,
        "aliquota_2" => 92,
        "aliquota_0_38" => 93,
        "aliquota_4" => 94,
        "aliquota_2_38" => 95,
        "aliquota_7" => 96,
        "aliquota_7_2" => 97, // Caso precise diferenciar
        "aliquota_7_38" => 98,
    ];

    protected $iofCode;


    /**
     * Mostrar o endereço do beneficiário abaixo da razão e CNPJ na ficha de compensação
     *
     * @var bool
     */
    protected $mostrarEnderecoFichaCompensacao = true;

    /**
     * Define os nomes das carteiras para exibição no boleto
     *
     * @var array
     */
    protected $carteirasNomes = [
        '101' => 'Cobrança Simples ECR',
        '102' => 'Cobrança Simples CSR',
        '201' => 'Penhor',
    ];

    /**
     * Define o valor do IOS - Seguradoras (Se 7% informar 7. Limitado a 9%) - Demais clientes usar 0 (zero)
     *
     * @var int
     */
    protected $ios = 0;

    /**
     * Variaveis adicionais.
     *
     * @var array
     */
    public $variaveis_adicionais = [
        'esconde_uso_banco' => true,
    ];

    /**
     * Código do cliente.
     *
     * @var int
     */
    protected $codigoCliente;

    /**
     * Conta cosmos
     *
     * @var [type]
     */
    protected $contaCosmos;

    /**
     * portfolio Os 3 últimos dígitos do campo de identificação da empresa no CITIBANK.
     *
     * @var [type]
     */
    protected $portifolio;

    /**
     * Retorna o campo Agência/Beneficiário do boleto
     *
     * @return string
     */
    public function getAgenciaCodigoBeneficiario()
    {
        $agencia = rtrim(sprintf('%s-%s', $this->getAgencia(), $this->getAgenciaDv()), '-');

        return sprintf('%s / %s', $agencia, $this->getCodigoCliente());
    }

    /**
     * Retorna o código da carteira
     * @return string
     */
    public function getCarteiraNumero()
    {
        return $this->carteira;
    }

    /**
     * Retorna o código do cliente.
     *
     * @return int
     */
    public function getCodigoCliente()
    {
        return $this->codigoCliente;
    }

    /**
     * Define o código do cliente.
     *
     * @param int $codigoCliente
     *
     * @return AbstractBoleto
     */
    public function setCodigoCliente($codigoCliente)
    {
        $this->codigoCliente = $codigoCliente;

        return $this;
    }

    /**
     * Define o código da carteira (Com ou sem registro)
     *
     * @param string $carteira
     * @return AbstractBoleto
     * @throws ValidationException
     */
    public function setCarteira($carteira)
    {
        return parent::setCarteira($carteira);
    }

    /**
     * Define o valor do IOS
     *
     * @param int $ios
     */
    public function setIos($ios)
    {
        $this->ios = $ios;
    }

    /**
     * Retorna o atual valor do IOS
     *
     * @return int
     */
    public function getIos()
    {
        return $this->ios;
    }

    /**
     * Seta dia para baixa automática
     *
     * @param int $baixaAutomatica
     *
     * @return Santander
     * @throws ValidationException
     */
    public function setDiasBaixaAutomatica($baixaAutomatica)
    {
        if ($this->getDiasProtesto() > 0) {
            throw new ValidationException('Você deve usar dias de protesto ou dias de baixa, nunca os 2');
        }
        if (! in_array($baixaAutomatica, [15, 30])) {
            throw new ValidationException('O Banco Santander so aceita 15 ou 30 dias após o vencimento para baixa automática');
        }
        $baixaAutomatica = (int) $baixaAutomatica;
        $this->diasBaixaAutomatica = $baixaAutomatica > 0 ? $baixaAutomatica : 0;

        return $this;
    }

    /**
     * Gera o Nosso Número.
     *
     * @return string
     */
    protected function gerarNossoNumero()
    {
        return Util::numberFormatGeral($this->getNumero(), 9);
    }

    /**
     * Método que retorna o nosso numero usado no boleto. alguns bancos possuem algumas diferenças.
     *
     * @return string
     */
    public function getNossoNumeroBoleto()
    {
        return substr($this->getNossoNumero(), 0, -1) . '-' . substr($this->getNossoNumero(), -1);
    }

    /**
     * @param $id
     * @return string
     * @throws ValidationException
     */
    protected function validateId($id)
    {
        if (! preg_match('/^[a-zA-Z0-9]{25,36}$/', $id)) {
            throw new ValidationException('ID/TXID do boleto é inválido, Os caracteres aceitos neste contexto são: A-Z, a-z, 0-9, não pode conter brancos e nulos, com o mínimo de 26 caracteres e no máximo 35 caracteres');
        }

        return $id;
    }

    /**
     * Método para gerar o código da posição de 20 a 44
     *
     * @return string
     */
    protected function getCampoLivre()
    {
        if ($this->campoLivre) {
            return $this->campoLivre;
        }

        $campoLivre = $this->campoLivre = '7' . substr($this->getAgencia(), 0, 4)  . substr($this->getAgencia(), -1)
            // . $this->getIndiceContaCosmo()
            . Util::numberFormatGeral($this->getCodigoCliente(), 9)
            . Util::numberFormatGeral($this->getNossoNumero(), 9)
            . '2'; //campo cobranca registrada;
        return $campoLivre;
    }

    // public function getLinhaDigitavel()
    // {

    //     $this->campoLinhaDigitavel = $this->setLinha();
    //     return $this->campoLinhaDigitavel = Util::formatLinhaDigitavel($this->campoLinhaDigitavel);
    // }

    // public function setLinha()
    // {
    //     $barCode = $this->getCodigoBarras();
    //     $linhaDigitavel = $this->getCodigoBanco() . $this->getMoeda() . '3'
    //         . $this->getPortifolio() . $this->getDadosContaCosmo(1, 1)
    //         . $this->getDadosContaCosmo(2, 5)
    //         . $this->getSeqContaCosmo()
    //         . $this->getDigVerficadorContaCosmo()
    //         . Util::numberFormatGeral($this->getNossoNumero(), 12)
    //         . Util::fatorVencimento($this->getDataVencimento())
    //         .  Util::numberFormatGeral($this->getValor(), 10);
    //     $campo1 = substr($linhaDigitavel, 0, 9);
    //     $campo1 .= Util::modulo10($campo1);

    //     $campo2 = substr($linhaDigitavel, 9, 10);
    //     $campo2 .= Util::modulo10($campo2);

    //     $campo3 = substr($linhaDigitavel, 19, 10);
    //     $campo3 .= Util::modulo10($campo3);

    //     $campo4 = substr($barCode, 4, 1);


    //     $campo5 = substr($linhaDigitavel, 29, 14);

    //     return $campo1 . $campo2 . $campo3 . $campo4 . $campo5;
    // }



    /**
     * Método onde qualquer boleto deve extender para gerar o código da posição de 20 a 44
     *
     * @param $campoLivre
     *
     * @return array
     */
    public static function parseCampoLivre($campoLivre)
    {
        return [];
    }
}
