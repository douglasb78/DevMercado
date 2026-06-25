(function () {
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function brl(v) {
    return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
  }

  async function carregar(pedidoId, container, opts) {
    opts = opts || {};
    if (container.dataset.carregado === '1' || container.dataset.carregando === '1') return;
    container.dataset.carregando = '1';
    container.innerHTML = '<div class="pd-loading">Carregando itens...</div>';

    try {
      const resp = await fetch('/api/pedidos.php?id=' + encodeURIComponent(pedidoId), {
        headers: { 'Accept': 'application/json' }
      });
      const data = await resp.json();
      if (!resp.ok || data.error) {
        container.innerHTML = '<div class="pd-erro">' + esc(data.error || 'Erro ao carregar itens.') + '</div>';
        container.dataset.carregando = '0';
        return;
      }

      const itens = data.itens || [];
      const podeEditar = !!opts.editavel;

      let datas = [];
      if (data.data_envio && data.data_envio !== '—') datas.push('Enviado em: ' + esc(data.data_envio));
      if (data.data_cancelamento && data.data_cancelamento !== '—') datas.push('Cancelado em: ' + esc(data.data_cancelamento));
      const datasHtml = datas.length ? '<div class="pd-datas">' + datas.join(' &nbsp;·&nbsp; ') + '</div>' : '';

      let linhas = '';
      itens.forEach(function (it) {
        const foto = it.foto || 'https://placehold.co/80x80?text=Prod';
        const sub = Number(it.preco) * Number(it.quantidade);
        const fotoJs = JSON.stringify(foto);
        const nomeJs = JSON.stringify(it.nome || '');
        linhas +=
          '<tr>' +
          '<td><img src="' + esc(foto) + '" alt="' + esc(it.nome) + '" onclick=\'abrirImagem(' + esc(fotoJs) + ',' + esc(nomeJs) + ')\'></td>' +
          '<td>' + esc(it.nome) + '</td>' +
          '<td>' + esc(it.descricao || '—') + '</td>' +
          '<td>' + esc(it.quantidade) + '</td>' +
          '<td>' + brl(it.preco) + '</td>' +
          '<td>' + brl(sub) + '</td>' +
          (podeEditar
            ? '<td><button type="button" onclick=\'abrirModalEditarItemPedido(' + Number(data.id) + ',' + Number(it.produto_id) + ',' + nomeJs + ',' + Number(it.quantidade) + ')\'>Editar Qtd</button></td>'
            : '') +
          '</tr>';
      });

      if (!linhas) {
        linhas = '<tr><td colspan="' + (podeEditar ? 7 : 6) + '" style="text-align:center;color:#888">Sem itens.</td></tr>';
      }

      container.innerHTML =
        datasHtml +
        '<table class="pd-table">' +
        '<thead><tr><th>Foto</th><th>Produto</th><th>Descrição</th><th>Qtd</th><th>Unitário</th><th>Subtotal</th>' +
        (podeEditar ? '<th>Ação</th>' : '') +
        '</tr></thead><tbody>' + linhas + '</tbody></table>';

      container.dataset.carregado = '1';
      container.dataset.carregando = '0';
    } catch (e) {
      container.innerHTML = '<div class="pd-erro">Erro de rede ao carregar os itens.</div>';
      container.dataset.carregando = '0';
    }
  }

  function toggleLinha(rowId, pedidoId, containerId, editavel) {
    const row = document.getElementById(rowId);
    if (!row) return;
    row.classList.toggle('visible');
    const cont = document.getElementById(containerId);
    if (cont && row.classList.contains('visible')) {
      carregar(pedidoId, cont, { editavel: !!editavel });
    }
  }

  window.PedidoDetalhe = { carregar: carregar, toggleLinha: toggleLinha };
})();
