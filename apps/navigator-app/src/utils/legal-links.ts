import Config from 'react-native-config';
import { Alert, Linking } from 'react-native';

export async function openOperatorLegalLink(kind: 'privacy' | 'terms'): Promise<boolean> {
    const url = kind === 'privacy' ? Config.PRIVACY_POLICY_URL : Config.TERMS_URL;
    if (typeof url !== 'string' || !/^https:\/\/[^\s/]+\.[^\s/]+/i.test(url)) {
        Alert.alert('Document unavailable', 'Your organization has not configured this legal document.');
        return false;
    }
    if (!(await Linking.canOpenURL(url))) {
        Alert.alert('Document unavailable', 'This document cannot be opened on this device.');
        return false;
    }
    await Linking.openURL(url);
    return true;
}
