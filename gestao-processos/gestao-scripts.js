// ===== FUNÇÕES GERAIS =====
function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function fecharModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

// Fechar modal ao clicar fora ou pressionar ESC
document.addEventListener('DOMContentLoaded', function() {
    // Fechar modal ao clicar fora
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal(this.id);
            }
        });
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(modal => {
                fecharModal(modal.id);
            });
        }
    });

    // Animações de entrada
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
});

// ===== FUNÇÕES PARA CHECKLIST =====
function adicionarChecklistItem() {
    const container = document.getElementById('checklist-container');
    const newItem = document.createElement('div');
    newItem.className = 'checklist-template-item';
    newItem.innerHTML = `
        <div class="checklist-header">
            <input type="text" name="checklist_titulo[]" 
                   placeholder="Título da etapa" class="checklist-titulo-input" required>
            <button type="button" class="btn-remove-checklist" onclick="removerChecklistItem(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <textarea name="checklist_descricao[]" 
                  placeholder="Descrição detalhada desta etapa..."
                  class="checklist-descricao-input"></textarea>
    `;
    container.appendChild(newItem);
}

function removerChecklistItem(button) {
    if (document.querySelectorAll('.checklist-template-item').length > 1) {
        button.closest('.checklist-template-item').remove();
    }
}

function marcarChecklist(checklistId, concluido, empresaId) {
    fetch('marcar_checklist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            checklist_id: checklistId,
            empresa_id: empresaId,
            concluido: concluido,
            usuario_id: window.usuarioId || 1 // Será definido no PHP
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar visualmente
            const item = document.querySelector(`input[onchange*="${checklistId}"]`).closest('.checklist-item');
            if (concluido) {
                item.classList.add('concluido');
            } else {
                item.classList.remove('concluido');
            }
            
            // Recarregar para atualizar informações
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            alert('Erro ao atualizar checklist: ' + data.message);
            // Reverter o checkbox
            event.target.checked = !concluido;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar checklist');
        event.target.checked = !concluido;
    });
}

function abrirModalObservacoes(checklistId, titulo) {
    document.getElementById('observacao_checklist_id').value = checklistId;
    document.getElementById('observacao_titulo').textContent = titulo;
    document.getElementById('modalObservacoes').style.display = 'flex';
}

// ===== FUNÇÕES PARA EMPRESAS =====
function filtrarEmpresas() {
    const searchTerm = document.getElementById('empresaSearch').value.toLowerCase();
    const empresas = document.querySelectorAll('.empresa-item');
    
    empresas.forEach(empresa => {
        const nome = empresa.getAttribute('data-nome') || '';
        const cnpj = empresa.getAttribute('data-cnpj') || '';
        const matches = nome.includes(searchTerm) || cnpj.includes(searchTerm);
        empresa.style.display = matches ? 'flex' : 'none';
    });
}

function selecionarTodasEmpresas() {
    document.querySelectorAll('.empresa-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
    atualizarContadorEmpresas();
}

function limparSelecaoEmpresas() {
    document.querySelectorAll('.empresa-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    atualizarContadorEmpresas();
}

function atualizarContadorEmpresas() {
    const selecionadas = document.querySelectorAll('.empresa-checkbox:checked').length;
    const contador = document.getElementById('contador-numero');
    if (contador) contador.textContent = selecionadas;
}

// ===== FUNÇÕES PARA PROCESSOS =====
function gerarCodigoAutomatico() {
    const codigoInput = document.getElementById('codigo');
    if (codigoInput) {
        const timestamp = new Date().getTime().toString().slice(-4);
        codigoInput.value = `PRC-${timestamp}`;
    }
}

function atualizarResumen() {
    const empresasSelecionadas = document.querySelectorAll('.empresa-checkbox:checked').length;
    const tipoProcesso = document.getElementById('recorrente')?.value || 'unico';
    const prioridade = document.getElementById('prioridade')?.value || 'media';
    
    const summaryEmpresas = document.getElementById('summaryEmpresas');
    const summaryTipo = document.getElementById('summaryTipo');
    const summaryPrioridade = document.getElementById('summaryPrioridade');
    
    if (summaryEmpresas) summaryEmpresas.textContent = empresasSelecionadas;
    if (summaryTipo) summaryTipo.textContent = tipoProcesso === 'unico' ? 'Único' : 
        tipoProcesso.charAt(0).toUpperCase() + tipoProcesso.slice(1);
    if (summaryPrioridade) summaryPrioridade.textContent = 
        prioridade.charAt(0).toUpperCase() + prioridade.slice(1);
}

function validarFormularioProcesso(e) {
    const empresasSelecionadas = document.querySelectorAll('.empresa-checkbox:checked').length;
    
    if (empresasSelecionadas === 0) {
        e.preventDefault();
        alert('Por favor, selecione pelo menos uma empresa para o processo.');
        document.querySelector('.empresas-section')?.scrollIntoView({ 
            behavior: 'smooth',
            block: 'center'
        });
        return false;
    }
    
    return true;
}

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar contadores
    atualizarContadorEmpresas();
    gerarCodigoAutomatico();
    atualizarResumen();
    
    // Event listeners para empresas
    document.querySelectorAll('.empresa-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', atualizarContadorEmpresas);
    });
    
    // Event listener para busca de empresas
    const searchInput = document.getElementById('empresaSearch');
    if (searchInput) {
        searchInput.addEventListener('input', filtrarEmpresas);
    }
    
    // Event listener para recorrência
    const selectRecorrente = document.getElementById('recorrente');
    if (selectRecorrente) {
        selectRecorrente.addEventListener('change', function() {
            const dataInicioContainer = document.getElementById('data_inicio_container');
            const recorrenciaInfo = document.getElementById('recorrenciaInfo');
            
            if (dataInicioContainer) {
                dataInicioContainer.style.display = this.value !== 'unico' ? 'block' : 'none';
            }
            if (recorrenciaInfo) {
                recorrenciaInfo.style.display = this.value !== 'unico' ? 'block' : 'none';
            }
            
            atualizarResumen();
        });
    }
});