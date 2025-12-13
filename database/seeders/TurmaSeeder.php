<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Turma;
use Carbon\Carbon;

class TurmaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $turmas = [
            [
                'title' => 'Mentoria Polícia da Câmara',
                'description' => 'Prepare-se para o concurso da Polícia Legislativa da Câmara dos Deputados com metodologia exclusiva e acompanhamento personalizado.',
                'checkout_url' => 'https://checkout.example.com/policia-camara',
                'start_date' => Carbon::now()->addDays(15),
                'available_slots' => 30,
                'status' => 'aberta',
            ],
            [
                'title' => 'Mentoria ALEGO',
                'description' => 'Conquiste sua aprovação na Assembleia Legislativa de Goiás com estratégias comprovadas e suporte contínuo.',
                'checkout_url' => 'https://checkout.example.com/alego',
                'start_date' => Carbon::now()->addDays(20),
                'available_slots' => 25,
                'status' => 'aberta',
            ],
            [
                'title' => 'PMDF - Polícia Militar do Distrito Federal',
                'description' => 'Turma especial para o concurso da PMDF com foco em estratégia, disciplina e aprovação garantida.',
                'checkout_url' => 'https://checkout.example.com/pmdf',
                'start_date' => Carbon::now()->addDays(10),
                'available_slots' => 40,
                'status' => 'aberta',
            ],
            [
                'title' => 'PMGO - Polícia Militar de Goiás',
                'description' => 'Preparação completa para o concurso da Polícia Militar de Goiás com aulas ao vivo e material exclusivo.',
                'checkout_url' => 'https://checkout.example.com/pmgo',
                'start_date' => Carbon::now()->addDays(25),
                'available_slots' => 35,
                'status' => 'aberta',
            ],
            [
                'title' => 'Concurso Senado Federal',
                'description' => 'Prepare-se para os concursos do Senado Federal com mentoria especializada e cronograma personalizado.',
                'checkout_url' => 'https://checkout.example.com/senado',
                'start_date' => Carbon::now()->addDays(30),
                'available_slots' => 20,
                'status' => 'aberta',
            ],
            [
                'title' => 'TRF - Tribunal Regional Federal',
                'description' => 'Mentoria focada nos concursos dos Tribunais Regionais Federais com ênfase em Direito e Jurisprudência.',
                'checkout_url' => 'https://checkout.example.com/trf',
                'start_date' => Carbon::now()->addDays(35),
                'available_slots' => 15,
                'status' => 'aberta',
            ],
            [
                'title' => 'Carreira Policial Federal',
                'description' => 'Preparação intensiva para Polícia Federal com simulados, resolução de questões e acompanhamento semanal.',
                'checkout_url' => 'https://checkout.example.com/pf',
                'start_date' => Carbon::now()->addDays(18),
                'available_slots' => 28,
                'status' => 'aberta',
            ],
            [
                'title' => 'Concursos Legislativos Estaduais',
                'description' => 'Turma voltada para diversos concursos das Assembleias Legislativas estaduais com conteúdo unificado.',
                'checkout_url' => 'https://checkout.example.com/legislativos',
                'start_date' => Carbon::now()->addDays(22),
                'available_slots' => 32,
                'status' => 'aberta',
            ],
            [
                'title' => 'Preparação TCU - Tribunal de Contas',
                'description' => 'Mentoria exclusiva para concursos dos Tribunais de Contas com foco em Administração e Controle.',
                'checkout_url' => 'https://checkout.example.com/tcu',
                'start_date' => Carbon::now()->addDays(28),
                'available_slots' => 18,
                'status' => 'aberta',
            ],
            [
                'title' => 'Carreiras Jurídicas - Magistratura',
                'description' => 'Preparação completa para concursos de Magistratura com professores especializados e estudo dirigido.',
                'checkout_url' => 'https://checkout.example.com/magistratura',
                'start_date' => Carbon::now()->addDays(40),
                'available_slots' => 12,
                'status' => 'aberta',
            ],
        ];

        foreach ($turmas as $turma) {
            Turma::create($turma);
        }
    }
}
