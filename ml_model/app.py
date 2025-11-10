import os
import sys
import joblib
import logging
import tempfile
from flask import Flask, request, jsonify
from werkzeug.utils import secure_filename

from preprocessing import unified_preprocess_text
from extractor import extract_details
from pdf_processor import (
    extract_text_from_pdf,
    initialize_easyocr_reader,
    EASYOCR_READER,
    EASYOCR_ERROR
)

# Configuração de logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

# Adiciona um manipulador para forçar a saída para o terminal (stdout)
handler = logging.StreamHandler(sys.stdout)
handler.setLevel(logging.INFO)
formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
logger.addHandler(handler)
logger.propagate = False # Evita logs duplicados se o basicConfig já estiver funcionando

# --- Configuração de Caminhos ---
try:
    BASE_DIR = os.path.dirname(os.path.abspath(__file__))
    MODEL_PATH = os.path.join(BASE_DIR, 'fatura_classifier_model.pkl')
    VECTORIZER_PATH = os.path.join(BASE_DIR, 'tfidf_vectorizer.pkl')
except NameError:
    BASE_DIR = '.'
    MODEL_PATH = 'fatura_classifier_model.pkl'
    VECTORIZER_PATH = 'tfidf_vectorizer.pkl'

app = Flask(__name__)

# --- Carregamento de Dependências ---
model = None
vectorizer = None

def load_dependencies():
    """Carrega o modelo, vetorizador e inicializa o leitor OCR."""
    global model, vectorizer
    model_loaded = False
    try:
        logger.info(f"Tentando carregar o modelo de: {MODEL_PATH}")
        if not os.path.exists(MODEL_PATH):
            raise FileNotFoundError(f"Arquivo do modelo não encontrado: {MODEL_PATH}")
        model = joblib.load(MODEL_PATH)
        logger.info("Modelo carregado com sucesso.")

        logger.info(f"Tentando carregar o vetorizador de: {VECTORIZER_PATH}")
        if not os.path.exists(VECTORIZER_PATH):
            raise FileNotFoundError(f"Arquivo do vetorizador não encontrado: {VECTORIZER_PATH}")
        vectorizer = joblib.load(VECTORIZER_PATH)
        logger.info("Vetorizador carregado com sucesso.")
        model_loaded = True
    except (FileNotFoundError, Exception) as e:
        logger.error(f"ERRO CRÍTICO ao carregar modelo/vetorizador: {e}")

    # Inicializa o leitor OCR a partir do módulo centralizado
    initialize_easyocr_reader()

    return model_loaded

def _process_pdf_file(pdf_path, original_filename):
    """Função auxiliar que processa o PDF usando o módulo centralizado."""
    try:
        # Usa a função de extração centralizada
        full_text = extract_text_from_pdf(pdf_path)

        if not full_text:
            error_msg = f'Todas as tentativas de extração de texto para {original_filename} falharam.'
            if EASYOCR_ERROR:
                error_msg += f' A inicialização do OCR falhou: {EASYOCR_ERROR}'
            logger.error(error_msg)
            return jsonify({'error': error_msg, 'status': 'extraction_failed'}), 500

        preprocessed_text = unified_preprocess_text(full_text)
        if not preprocessed_text.strip():
            return jsonify({'error': 'Texto extraído ficou vazio após pré-processamento.', 'status': 'empty_after_preprocess'}), 400

        text_features = vectorizer.transform([preprocessed_text])
        category = model.predict(text_features)[0]
        prediction_proba = model.predict_proba(text_features)
        max_proba = float(max(prediction_proba[0]))

        logger.info(f"Classificação: {category} (confiança: {max_proba:.3f}) para {original_filename}")

        # Log para depuração da extração
        try:
            debug_log_path = os.path.join(BASE_DIR, 'extraction_debug.log')
            with open(debug_log_path, 'a', encoding='utf-8') as f:
                f.write(f"--- START: {original_filename} ---\n")
                f.write(full_text)
                f.write(f"\n--- END: {original_filename} ---\n\n")
        except Exception as e:
            logger.error(f"Falha ao escrever no log de depuração: {e}")

        details = extract_details(full_text, category)

        # Validação para o campo 'conta_mes' na categoria 'energia'
        if category == 'energia' and not details.get('conta_mes'):
            error_msg = f"Campo 'conta_mes' não encontrado ou vazio para a fatura de energia: {original_filename}"
            logger.error(error_msg)
            return jsonify({'error': error_msg, 'status': 'validation_failed'}), 400

        # Compatibilidade: o extractor agora pode retornar chaves com nomes novos
        # (por exemplo 'emissão'). Mantenha também as chaves antigas
        # que o restante do sistema (PHP/DB) espera ('emissao', 'data_emissao').
        try:
            # Copia 'emissão' (com acento) para 'emissao' (sem acento) e 'data_emissao'
            if 'emissão' in details:
                if not details.get('emissao'):
                    details['emissao'] = details.get('emissão')
                if not details.get('data_emissao'):
                    details['data_emissao'] = details.get('emissão')
        except Exception:
            logger.warning('Falha ao aplicar mapeamento de compatibilidade nas chaves de detalhes.')

        logger.info(f"Detalhes extraídos: {details}")

        return jsonify({
            'category': category,
            'confidence': max_proba,
            'details': details,
        'identificador_unidade': details.get('codigo_instalacao'), # Adiciona o identificador para o PHP
            'status': 'success'
        })

    except Exception as e:
        logger.error(f"Erro inesperado ao processar PDF {original_filename}: {str(e)}")
        return jsonify({'error': f'Erro ao processar PDF: {str(e)}', 'status': 'pdf_processing_error'}), 500

# Carrega as dependências na inicialização
dependencies_loaded = load_dependencies()

# --- Endpoints da API ---

@app.route('/', methods=['GET'])
def index():
    return jsonify({
        'api_name': 'API de Classificação de Faturas',
        'status': 'online',
        'model_status': 'Carregado' if dependencies_loaded else 'Não Carregado',
        'ocr_status': 'Disponível' if EASYOCR_READER else f'Indisponível: {EASYOCR_ERROR}'
    })

@app.route('/health', methods=['GET'])
def health_check():
    healthy = dependencies_loaded and model and vectorizer
    return jsonify({
        'status': 'healthy' if healthy else 'unhealthy',
        'model_loaded': model is not None,
        'vectorizer_loaded': vectorizer is not None,
        'ocr_reader_loaded': EASYOCR_READER is not None
    }), 200 if healthy else 500

@app.route('/classify_pdf', methods=['POST'])
def classify_pdf():
    if not dependencies_loaded:
        return jsonify({'error': 'Modelo de classificação não carregado.', 'status': 'model_not_loaded'}), 500
    if 'file' not in request.files:
        return jsonify({'error': 'Arquivo PDF não enviado.', 'status': 'no_file'}), 400
    
    file = request.files['file']
    if file.filename == '':
        return jsonify({'error': 'Nome do arquivo vazio.', 'status': 'empty_filename'}), 400

    filename = secure_filename(file.filename)
    temp_pdf_path = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix='.pdf') as temp_pdf:
            file.save(temp_pdf.name)
            temp_pdf_path = temp_pdf.name
        return _process_pdf_file(temp_pdf_path, filename)
    finally:
        if temp_pdf_path and os.path.exists(temp_pdf_path):
            os.remove(temp_pdf_path)

@app.route('/predict', methods=['POST'])
def predict():
    if not dependencies_loaded:
        return jsonify({'error': 'Modelo de classificação não carregado.', 'status': 'model_not_loaded'}), 500
    
    data = request.get_json()
    if not data or 'text' not in data or not isinstance(data['text'], str):
        return jsonify({'error': 'JSON inválido ou campo "text" ausente/inválido.', 'status': 'invalid_input'}), 400

    text_input = data['text'].strip()
    if not text_input:
        return jsonify({'error': 'Campo "text" não pode estar vazio.', 'status': 'empty_text'}), 400

    preprocessed_text = unified_preprocess_text(text_input)
    text_features = vectorizer.transform([preprocessed_text])
    category = model.predict(text_features)[0]
    prediction_proba = model.predict_proba(text_features)
    max_proba = float(max(prediction_proba[0]))
    
    logger.info(f"Predição: {category} (confiança: {max_proba:.3f})")
    details = extract_details(text_input, category)
    # Compatibilidade para previsões ad-hoc (mesma lógica que para arquivos)
    try:
        if 'emissao' in details:
            if not details.get('emissao'):
                details['emissao'] = details.get('emissao')
            if not details.get('data_emissao'):
                details['data_emissao'] = details.get('emissao')
    except Exception:
        logger.warning('Falha ao aplicar mapeamento de compatibilidade nas chaves de detalhes (predict).')

    return jsonify({
        'category': category,
        'confidence': max_proba,
        'details': details,
        'identificador_unidade': details.get('codigo_instalacao'), # Adiciona o identificador para o PHP
        'status': 'success'
    })

# --- Tratamento de Erros e Debug ---

@app.errorhandler(404)
def not_found(error):
    return jsonify({'error': 'Endpoint não encontrado', 'status': 'not_found'}), 404

@app.errorhandler(500)
def internal_error(error):
    return jsonify({'error': 'Erro interno do servidor', 'status': 'internal_error'}), 500

if __name__ == '__main__':
    # Forçando o modo de debug e uma nova porta para o teste
    debug_mode = True
    app.debug = debug_mode
    port = 5001

    if app.debug:
        @app.route('/debug/process_file', methods=['POST'])
        def debug_process_file():
            if not dependencies_loaded:
                return jsonify({'error': 'Modelo não carregado.'}), 500
            
            data = request.get_json()
            if not data or 'file_path' not in data:
                return jsonify({'error': 'JSON inválido ou campo "file_path" ausente.'}), 400
            
            file_path = data['file_path']
            if not os.path.isabs(file_path):
                # Constrói o caminho absoluto a partir da raiz do projeto
                file_path = os.path.join(BASE_DIR, file_path)

            if not os.path.exists(file_path):
                return jsonify({'error': f'Arquivo não encontrado: {file_path}'}), 404

            filename = os.path.basename(file_path)
            logger.info(f"[DEBUG] Processando arquivo local: {file_path}")
            return _process_pdf_file(file_path, filename)

    host = os.getenv('FLASK_HOST', '127.0.0.1')
    # port foi definido acima
    
    logger.info(f"Iniciando aplicação Flask em {host}:{port} (debug={app.debug})")
    
    if not dependencies_loaded:
        logger.warning("ATENÇÃO: Aplicação iniciando sem o modelo de classificação carregado!")
    
    app.run(host=host, port=port)
