@forelse($alunos as $aluno)
<tr class="hover:bg-gray-50">
    <td class="px-4 py-3 text-sm font-medium text-gray-800 whitespace-nowrap">{{ $aluno->nome }}</td>
    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $aluno->email }}</td>
    <td class="px-4 py-3 text-sm whitespace-nowrap">{{ $aluno->ativo ? 'Ativo' : 'Inativo' }}</td>
    <td class="px-4 py-3 text-sm text-gray-700"><x-desempenho-badge :valor="$aluno->last_performance" /></td>
    <td class="px-4 py-3 text-sm text-gray-700"><x-desempenho-badge :valor="$aluno->last_question_volume" /></td>
    <td class="px-4 py-3 text-sm text-gray-700"><x-desempenho-badge :valor="$aluno->last_accuracy_rate" /></td>
    <td class="px-4 py-3 text-sm text-gray-700"><x-desempenho-badge :valor="$aluno->last_subjects" /></td>
    <td class="px-4 py-3 text-right">
        <a href="{{ route('alunos.edit', $aluno) }}" class="px-3 py-2 bg-primary hover:bg-primary-light text-white rounded text-sm transition">Editar</a>
    </td>
</tr>
@empty
<tr>
    <td colspan="9" class="px-4 py-8 text-center text-gray-600">
        {{ filled($busca ?? '') ? 'Nenhum aluno encontrado para essa busca.' : 'Nenhum aluno cadastrado.' }}
    </td>
</tr>
@endforelse