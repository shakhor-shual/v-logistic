// i18n.js - Internationalization module for Venture Industries

class I18n {
    constructor() {
        this.currentLang = 'en';
        this.translations = {};
        this.observers = [];
        this.supportedLangs = ['ru', 'en', 'pl', 'uk', 'ka'];
        this.langNames = {
            ru: 'Русский',
            en: 'English',
            pl: 'Polski',
            uk: 'Українська',
            ka: 'ქართული'
        };
    }
    
    /**
     * Detect browser language with priority for UI language
     */
    detectBrowserLanguage() {
        // Try to get language from browser
        const browserLang = navigator.language || navigator.userLanguage;
        const langCode = browserLang.split('-')[0].toLowerCase();
        
        // Check if language is supported
        if (this.supportedLangs.includes(langCode)) {
            return langCode;
        }
        
        // Check for Ukrainian special case
        if (langCode === 'ua') return 'uk';
        
        // Georgian special case
        if (langCode === 'ka' || langCode === 'ge') return 'ka';
        
        // Default to English
        return 'en';
    }
    
    /**
     * Load translations for a language
     */
    async loadLanguage(lang) {
        try {
            const timestamp = Date.now();
            const response = await fetch(`locales/${lang}.json?v=${timestamp}`);
            if (!response.ok) throw new Error(`Failed to load ${lang} translations`);
            
            this.translations[lang] = await response.json();
            this.currentLang = lang;
            
            // Save language preference
            localStorage.setItem('preferred_lang', lang);
            
            // Notify observers
            this.notifyObservers();
            
            return true;
        } catch (error) {
            console.error('Error loading language:', error);
            return false;
        }
    }
    
    /**
     * Initialize i18n - auto-detect language
     */
    async init() {
        // Try saved preference first
        const savedLang = localStorage.getItem('preferred_lang');
        
        if (savedLang && this.supportedLangs.includes(savedLang)) {
            await this.loadLanguage(savedLang);
        } else {
            // Auto-detect from browser
            const autoLang = this.detectBrowserLanguage();
            await this.loadLanguage(autoLang);
        }
    }
    
    /**
     * Get translated text with nested key support
     */
    t(key, params = {}) {
        const translation = this.translations[this.currentLang];
        if (!translation) return key;
        
        let text = this.getNestedValue(translation, key);
        if (!text) return key;
        
        // Replace parameters {{variable}}
        Object.keys(params).forEach(param => {
            text = text.replace(new RegExp(`{{${param}}}`, 'g'), params[param]);
        });
        
        return text;
    }
    
    /**
     * Get nested value from object
     */
    getNestedValue(obj, path) {
        const keys = path.split('.');
        let current = obj;
        
        for (const key of keys) {
            if (current && typeof current === 'object' && key in current) {
                current = current[key];
            } else {
                return null;
            }
        }
        
        return current;
    }
    
    /**
     * Change language
     */
    async setLanguage(lang) {
        if (!this.supportedLangs.includes(lang)) return false;
        if (lang === this.currentLang) return true;
        
        return await this.loadLanguage(lang);
    }
    
    /**
     * Get current language
     */
    getCurrentLanguage() {
        return this.currentLang;
    }
    
    /**
     * Get supported languages
     */
    getSupportedLanguages() {
        return this.supportedLangs;
    }
    
    /**
     * Add observer
     */
    addObserver(callback) {
        this.observers.push(callback);
    }
    
    /**
     * Notify observers
     */
    notifyObservers() {
        this.observers.forEach(callback => callback(this.currentLang));
    }
    
    /**
     * Update all DOM elements with translations
     */
    updateDOM() {
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            const translation = this.t(key);
            
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                element.placeholder = translation;
            } else {
                element.textContent = translation;
            }
        });
        
        // Update title
        const titleKey = document.documentElement.getAttribute('data-i18n-title');
        if (titleKey) {
            document.title = this.t(titleKey);
        }
        
        // Update meta description
        const metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc && metaDesc.getAttribute('data-i18n')) {
            metaDesc.content = this.t(metaDesc.getAttribute('data-i18n'));
        }
    }
}

// Create global instance
const i18n = new I18n();