import pandas as pd
import numpy as np
import json
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import classification_report, accuracy_score, confusion_matrix
import joblib
import os
import logging
import argparse

# Importações centralizadas para consistência
from pdf_processor import extract_text_from_pdf, initialize_easyocr_reader
from preprocessing import unified_preprocess_text

# Configuração de logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)
CACHE_FILE = 'training_cache.json'

def build_dataset_from_pdfs(base_dir, force_rebuild=False):
    """Constrói um DataFrame a partir de PDFs na pasta training_data."""
    training_data_path = os.path.join(base_dir, 'training_data')
    cache_path = os.path.join(base_dir, CACHE_FILE)

    if not os.path.isdir(training_data_path):
        logger.error(f"Diretório de treinamento '{training_data_path}' não encontrado.")
        return None

    # Tenta carregar do cache se não for forçado a reconstruir
    if not force_rebuild and os.path.exists(cache_path):
        logger.info(f"Carregando dataset do cache: {cache_path}")
        try:
            with open(cache_path, 'r', encoding='utf-8') as f:
                data = json.load(f)
            return pd.DataFrame(data)
        except (json.JSONDecodeError, Exception) as e:
            logger.warning(f"Não foi possível ler o cache ({e}). Reconstruindo do zero.")

    logger.info("=== Construindo Dataset a partir dos PDFs ===")
    data = []
    categories = [d for d in os.listdir(training_data_path) if os.path.isdir(os.path.join(training_data_path, d))]

    for category in categories:
        category_path = os.path.join(training_data_path, category)
        logger.info(f"Processando categoria: '{category}'")
        files_in_category = [f for f in os.listdir(category_path) if f.lower().endswith('.pdf')]
        if not files_in_category:
            logger.warning(f"    - Nenhum arquivo PDF encontrado em '{category}'.")
            continue

        for filename in files_in_category:
            if filename.lower().endswith('.pdf'):
                pdf_path = os.path.join(category_path, filename)
                # Usa a função de extração centralizada para consistência
                text = extract_text_from_pdf(pdf_path)
                if text:
                    data.append({'text': text, 'category': category})
                else:
                    logger.warning(f"    - Texto não extraído de {filename}.")

    # Salva os dados extraídos no cache para uso futuro
    try:
        with open(cache_path, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=4)
        logger.info(f"Dataset salvo no cache: {cache_path}")
    except Exception as e:
        logger.error(f"Erro ao salvar o cache do dataset: {e}")

    if not data:
        logger.error("Nenhum dado foi extraído dos PDFs. O dataset está vazio.")
        return None

    return pd.DataFrame(data)

def validate_dataset(df):
    """Valida o dataset e fornece estatísticas úteis."""
    logger.info("=== Validação do Dataset ===")
    
    logger.info(f"Total de amostras: {len(df)}")
    logger.info(f"Colunas presentes: {list(df.columns)}")
    
    null_counts = df.isnull().sum()
    if null_counts.any():
        logger.warning(f"Valores nulos encontrados:\n{null_counts}")
    
    class_counts = df['category'].value_counts()
    logger.info("Distribuição das classes:")
    for category, count in class_counts.items():
        percentage = (count / len(df)) * 100
        logger.info(f"  {category}: {count} amostras ({percentage:.1f}%)")
    
    min_samples = class_counts.min()
    max_samples = class_counts.max()
    
    if min_samples < 5: # Reduzido para 5 para permitir treinamento com menos dados iniciais
        logger.warning(f"ATENÇÃO: Alguma classe tem poucas amostras ({min_samples}). O modelo pode não generalizar bem. Considere coletar mais dados.")
    
    if max_samples / max(1, min_samples) > 10: # Aumentado para 10
        logger.warning(f"ATENÇÃO: Dataset pode estar desbalanceado. Razão max/min: {max_samples/max(1, min_samples):.1f}")
    
    if 'processed_text' in df.columns:
        text_lengths = df['processed_text'].str.len()
        # Verifica se algum texto processado é muito curto, indicando má extração.
        short_text_threshold = 50 # Menos de 50 caracteres é um sinal de alerta.
        short_texts = df[text_lengths < short_text_threshold]
        if not short_texts.empty:
            logger.warning(f"ATENÇÃO: {len(short_texts)} arquivos resultaram em texto extraído muito curto (<{short_text_threshold} caracteres), indicando baixa qualidade do PDF ou falha na extração. Estes arquivos serão ignorados no treinamento.")
            # Remove as amostras de baixa qualidade do DataFrame
            df.drop(short_texts.index, inplace=True)
        logger.info(f"Comprimento do texto processado - Média: {text_lengths.mean():.1f}, Min: {text_lengths.min()}, Max: {text_lengths.max()}")
    
    return True

def create_vectorizer():
    """Cria um vetorizador TF-IDF. O pré-processamento já foi feito."""
    vectorizer = TfidfVectorizer(
        max_features=5000,
        min_df=2,
        max_df=0.8,
        ngram_range=(1, 2)
    )
    return vectorizer

def train_and_evaluate_model(X_train, X_test, y_train, y_test):
    """Treina o modelo e avalia sua performance."""
    
    logger.info("=== Treinamento do Modelo ===")
    
    classifier = LogisticRegression(
        random_state=42,
        max_iter=1000,
        C=1.0,
        class_weight='balanced'
    )
    
    classifier.fit(X_train, y_train)
    logger.info("Treinamento concluído.")
    
    cv_scores = cross_val_score(classifier, X_train, y_train, cv=5, scoring='accuracy')
    logger.info(f"Validação cruzada (5-fold) - Acurácia média: {cv_scores.mean():.3f} (±{cv_scores.std()*2:.3f})")
    
    y_pred = classifier.predict(X_test)
    test_accuracy = accuracy_score(y_test, y_pred)
    
    logger.info("=== Resultados no Conjunto de Teste ===")
    logger.info(f"Acurácia: {test_accuracy:.3f}")
    logger.info(f"\nRelatório de Classificação:\n{classification_report(y_test, y_pred)}")
    
    cm = confusion_matrix(y_test, y_pred)
    logger.info(f"Matriz de Confusão:\n{cm}")
    
    return classifier

def save_models(vectorizer, classifier, base_dir):
    """Salva o vetorizador e modelo de forma segura, com backup dos antigos."""
    import shutil
    vectorizer_path = os.path.join(base_dir, 'tfidf_vectorizer.pkl')
    model_path = os.path.join(base_dir, 'fatura_classifier_model.pkl')
    try:
        for path in [vectorizer_path, model_path]:
            if os.path.exists(path):
                backup_path = path + '.bak'
                shutil.copy2(path, backup_path)
                logger.info(f"Backup criado: {backup_path}")
        logger.info(f"Salvando o vetorizador em: {vectorizer_path}")
        joblib.dump(vectorizer, vectorizer_path)
        logger.info(f"Salvando o modelo em: {model_path}")
        joblib.dump(classifier, model_path)
        logger.info("Arquivos do modelo salvos com sucesso!")
        return True
    except Exception as e:
        logger.error(f"Erro ao salvar os modelos: {e}")
        return False

def test_saved_model(df, base_dir):
    """Testa o modelo salvo com uma amostra do dataset."""
    
    try:
        vectorizer_path = os.path.join(base_dir, 'tfidf_vectorizer.pkl')
        model_path = os.path.join(base_dir, 'fatura_classifier_model.pkl')
        
        loaded_vectorizer = joblib.load(vectorizer_path)
        loaded_model = joblib.load(model_path)
        
        logger.info("\n=== Teste do Modelo Salvo ===")
        for category in df['category'].unique():
            category_samples = df[df['category'] == category]
            if len(category_samples) > 0:
                sample = category_samples.sample(1).iloc[0]
                
                sample_text_processed = sample['processed_text']
                
                text_features = loaded_vectorizer.transform([sample_text_processed])
                prediction = loaded_model.predict(text_features)
                prediction_proba = loaded_model.predict_proba(text_features)
                confidence = max(prediction_proba[0])
                
                text_preview = sample['text'][:100].replace('\n', ' ')
                logger.info(f"Categoria Real: {sample['category']}")
                logger.info(f"Predição: {prediction[0]} (confiança: {confidence:.3f})")
                logger.info(f"Texto Original: '{text_preview}...'")
                logger.info("-" * 50)
        
        return True
        
    except Exception as e:
        logger.error(f"Erro no teste do modelo salvo: {e}")
        return False

def main():
    """Função principal do script de treinamento."""
    parser = argparse.ArgumentParser(description="Script de treinamento do classificador de faturas.")
    parser.add_argument(
        '--force-rebuild',
        action='store_true',
        help="Força a reconstrução do dataset a partir dos PDFs, ignorando o cache."
    )
    args = parser.parse_args()

    base_dir = os.path.dirname(__file__)
    
    # Inicializa o OCR
    initialize_easyocr_reader()

    # 1. Constrói o dataset a partir dos PDFs
    df = build_dataset_from_pdfs(base_dir, force_rebuild=args.force_rebuild)
    if df is None or df.empty:
        logger.error("Falha ao construir o dataset. Abortando treinamento.")
        return False

    # Salva o dataset gerado para inspeção
    dataset_path = os.path.join(base_dir, 'faturas_dataset.csv')
    try:
        df.to_csv(dataset_path, index=False, encoding='utf-8')
        logger.info(f"Dataset gerado e salvo em: {dataset_path}")
    except Exception as e:
        logger.error(f"Não foi possível salvar o dataset em CSV: {e}")

    # 2. Pré-processamento do texto
    logger.info("Iniciando pré-processamento do texto...")
    df['processed_text'] = df['text'].apply(unified_preprocess_text)
    logger.info("Pré-processamento concluído.")

    validate_dataset(df)
    
    if len(df) < 10:
        logger.error("Dataset muito pequeno (< 10 amostras). Colete mais dados antes de treinar.")
        return False

    # 3. Divisão dos dados
    logger.info("\n=== Divisão dos Dados ===")
    try:
        X_train, X_test, y_train, y_test = train_test_split(
            df['processed_text'],
            df['category'],
            test_size=0.2,
            random_state=42,
            stratify=df['category']
        )
        logger.info(f"Treino: {len(X_train)} amostras, Teste: {len(X_test)} amostras")
    except ValueError as e:
        logger.warning(f"Não foi possível usar estratificação: {e}. Usando divisão normal.")
        X_train, X_test, y_train, y_test = train_test_split(
            df['processed_text'],
            df['category'],
            test_size=0.2,
            random_state=42
        )

    # 4. Vetorização dos Dados
    logger.info("\n=== Vetorização dos Dados ===")
    vectorizer = create_vectorizer()
    try:
        X_train_vec = vectorizer.fit_transform(X_train)
        X_test_vec = vectorizer.transform(X_test)
        logger.info(f"Vetorização concluída. Shape treino: {X_train_vec.shape}")
    except Exception as e:
        logger.error(f"Erro na vetorização: {e}")
        return False

    # 5. Treinamento e Avaliação
    classifier = train_and_evaluate_model(X_train_vec, X_test_vec, y_train, y_test)

    # 6. Salvamento dos Modelos
    if not save_models(vectorizer, classifier, base_dir):
        return False

    # 7. Teste do Modelo Salvo
    test_saved_model(df, base_dir)
    logger.info("\n=== Treinamento Concluído com Sucesso! ===")
    return True

if __name__ == '__main__':
    success = main()
    if not success:
        exit(1)