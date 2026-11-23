import Config from 'react-native-config';
import { Alert, Linking } from 'react-native';
import { openOperatorLegalLink } from '../src/utils/legal-links';

jest.mock('react-native-config', () => ({ PRIVACY_POLICY_URL: '', TERMS_URL: '' }));

describe('operator legal links', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        Linking.canOpenURL = jest.fn().mockResolvedValue(true);
        Linking.openURL = jest.fn().mockResolvedValue(undefined);
        Alert.alert = jest.fn();
        Config.PRIVACY_POLICY_URL = '';
        Config.TERMS_URL = '';
    });
    it('does not open an upstream URL without configuration', async () => {
        expect(await openOperatorLegalLink('privacy')).toBe(false);
        expect(Linking.openURL).not.toHaveBeenCalled();
        expect(Alert.alert).toHaveBeenCalled();
    });
    it('rejects a non-HTTPS URL', async () => {
        Config.TERMS_URL = 'http://operator.example.test/terms';
        expect(await openOperatorLegalLink('terms')).toBe(false);
        expect(Linking.openURL).not.toHaveBeenCalled();
    });
    it('opens the configured HTTPS document', async () => {
        Config.PRIVACY_POLICY_URL = 'https://operator.example.test/privacy';
        expect(await openOperatorLegalLink('privacy')).toBe(true);
        expect(Linking.openURL).toHaveBeenCalledWith('https://operator.example.test/privacy');
    });
});
