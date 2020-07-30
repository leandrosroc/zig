<style>
.destaque1 {
  border:1px solid #e9ecef!important;
  background:#fffcf5;
  border-left:0!important;
  border-right:0!important;
  padding-top:10px;
}
</style>
<form method="post" action="<?php echo isset($pedido->id) ? BASEURL . '/pedido/update' : BASEURL . '/pedido/save'; ?>" enctype='multipart/form-data'>
  <!-- token de segurança -->
  <input type="hidden" name="_token" value="<?php echo TOKEN; ?>" />

  <div class="row">

    <?php if (isset($pedido->id)) : ?>
      <input type="hidden" name="id" value="<?php echo $pedido->id; ?>">
    <?php endif; ?>

    <div class="col-md-4">
      <div class="form-group">
        <label for="nome">Vendedor *</label>
        <input type="text" class="form-control" name="nome" id="nome" placeholder="Digite aqui..."
        value="<?php echo $usuario->nome;?>" disabled>
      </div>
    </div>

    <div class="col-md-4">
      <div class="form-group">
        <label for="id_cliente">Clientes *</label>
        <select class="form-control" name="id_cliente" id="id_cliente"
        onchange="enderecoPorIdCliente(this.value);">
          <option value="selecione">Selecione</option>
          <?php foreach ($clientes as $cliente) : ?>
            <option value="<?php echo $cliente->id; ?>">
              <?php echo $cliente->nome; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

     <div class="col-md-4">
      <div class="form-group">
        <label for="id_endereco">Endereços *</label>
        <select class="form-control" name="id_endereco" id="id_endereco">
          <option value="selecione">Selecione</option>
        </select>
      </div>
    </div>

    <div class="col-md-4 destaque1">
      <div class="form-group">
        <label for="id_produto">Produtos *</label>
        <select class="form-control" name="id_produto" id="id_produto">
          <option value="selecione">Selecione</option>
          <?php foreach ($produtos as $produto) : ?>
            <option value="<?php echo $produto->id; ?>">
              <?php echo $produto->nome; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="col-md-4 destaque1">
      <div class="form-group">
        <label for="quantidade">Quantidade *</label>
        <input type="text" class="form-control" name="quantidade" id="nome">
      </div>
    </div>

    <div class="col-md-4 destaque1">
      <div class="form-group">
        <a class="btn btn-success" style="margin-top:30px"
        onclick="return adicionarProduto($('#id_produto').val(), $('#quantidade').val())">
          <i class="fas fa-plus"></i> Adicionar
        </a>
      </div>
    </div>

  </div>
  <!--end row-->

  <button type="submit" class="btn btn-success btn-sm button-salvar-empresa"
  style="float:right">
    <i class="fas fa-save"></i> Salvar
  </button>

</form>

<script>
  function enderecoPorIdCliente(idCliente) {
    var rota = getDomain()+"/pedido/enderecoPorIdCliente/"+idCliente;
    $('#id_endereco').html("<option>Carregando...</option>");

    $.get(rota, function(data, status) {
      var enderecos = JSON.parse(data);
      var options = false;

      if (enderecos.length != 0) {
        $('#id_endereco').empty();

        $.each(enderecos, function(index, value) {
          options += "<option value='"+value.id+"'>"+value.endereco+"</option>";
        });

        $('#id_endereco').append(options);
      } else {
        $('#id_endereco').html("<option value='selecione'>Não encontrado</option>");
      }
    });
  }

  function adicionarProduto(idProduto, quantidade) {
    var rota = getDomain()+"/pedido/produto/"+idProduto;

    $.get(rota, function(data, status) {
      var produto = JSON.parse(data);

      console.log(produto);

    });

    return false;
  }
</script>